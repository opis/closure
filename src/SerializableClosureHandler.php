<?php
/* ===========================================================================
 * Copyright (c) 2020 Zindex Software
 *
 * Licensed under the MIT License
 * =========================================================================== */

namespace Opis\Closure;

use FFI, Closure;
use FFI\{CData, CType};

class SerializableClosureHandler
{
    /**
     * @var static|null
     */
    protected static ?SerializableClosureHandler $instance = null;

    /**
     * @var FFI
     */
    protected $lib;

    /**
     * @var CData
     */
    protected CData $executor;

    /**
     * @var int
     */
    protected int $callFrameSlotSize;

    /**
     * @param FFI $lib
     */
    final protected function __construct(FFI $lib)
    {
        // Set lib
        $this->lib = $lib;

        // Calculate call frame slot size
        $this->callFrameSlotSize = $this->getCallFrameSlotSize();

        // Get executor
        $this->executor = $this->getExecutor();

        // Apply patch only if needed
        if (!class_parents(Closure::class)) {
            $this->patch();
        }
    }

    /**
     * @param Closure $closure
     * @return array
     * @throws \ReflectionException
     */
    public function serializeClosure(Closure $closure): array
    {
        $reflector = new ReflectionClosure($closure);

        if ($reflector->isFromCallable()) {
            if ($reflector->isClassMethod()) {
                $ret = [$reflector->getClosureThis() ?? $reflector->getClosureScopeClass()->name, $reflector->name];
            } else {
                $ret = $reflector->name;
            }
            return ['func' => $ret];
        }

        $ret = [];
        $scope = $object = null;

        $ret['code'] = $reflector->getCode();

        if ($use = $reflector->getUseVariables()) {
            $ret['use'] = $use;
        }

        /*
        if ($reflector->isBindingRequired()) {
            $object = $reflector->getClosureThis();
            $scope = $reflector->getClosureScopeClass();
        } elseif ($reflector->isScopeRequired()) {
            $scope = $reflector->getClosureScopeClass();
        }*/
        $object = $reflector->getClosureThis();
        $scope = $reflector->getClosureScopeClass();

        if ($object) {
            $ret['this'] = $object;
        }

        if ($scope && !$scope->isInternal()) {
            $ret['scope'] = $scope->name;
        }

        return $ret;
    }

    /**
     * @param Closure $closure
     * @param array $data
     */
    public function unserializeClosure(Closure $closure, array $data): void
    {
        if (!$data) {
            throw new \RuntimeException("Invalid data");
        }

        if (isset($data['func'])) {
            $temp = Closure::fromCallable($data['func']);
        } else {
            $data += [
                'code' => null,
                'scope' => null,
                'this' => null,
            ];

            $temp = ClosureStream::eval($data['code'], $data['use'] ?? null);

            if ($data['this']) {
                $temp = $temp->bindTo($data['this'], $data['scope'] ?? 'static');
            } elseif ($data['scope']) {
                $temp = $temp->bindTo(null, $data['scope']);
            } else {
                $temp = $temp->bindTo(null, null);
            }
        }

        unset($data);

        $dst = $this->closure($closure);
        $src = $this->closure($temp);

        // Copy func
        $dst->func = $src->func;
        if ($dst->func->type === 2) { // user function
            $op_array = $dst->func->op_array;

            $op_array->refcount[0] = $op_array->refcount[0] + 1;
            if (!FFI::isNull($op_array->static_variables)) {
                $op_array->static_variables->gc->refcount++;
                $op_array->static_variables_ptr = FFI::addr($op_array->static_variables);
            }

            unset($op_array);

            // Remove run time cache reference from source
            $src->func->op_array->run_time_cache = null;
        }

        // Set $this
        if (!FFI::isNull($src->this_ptr->value->obj)) {
            $src->this_ptr->value->counted->gc->refcount++;
            // Set $this
            $dst->this_ptr->value->obj = $src->this_ptr->value->obj;
            // Set type
            $dst->this_ptr->u1->type_info = $src->this_ptr->u1->type_info;
        }

        // Set called scope ptr
        if (!FFI::isNull($src->called_scope)) {
            $dst->called_scope = $src->called_scope;
        }

        // Set internal handler ptr
        if (!FFI::isNull($src->orig_internal_handler)) {
            $dst->orig_internal_handler = $src->orig_internal_handler;
        }
    }

    /**
     * ZEND_CALL_FRAME_SLOT
     * @return int
     */
    protected function getCallFrameSlotSize(): int
    {
        $zed = $this->alignedSize(FFI::sizeof($this->lib->type('zend_execute_data')));
        $zval = $this->alignedSize(FFI::sizeof($this->lib->type('zval')));

        return intdiv($zed + $zval - 1, $zval);
    }

    /**
     * ZEND_MM_ALIGNED_SIZE
     * @param int $size
     * @param int $align
     * @return int
     */
    protected function alignedSize(int $size, int $align = 8): int
    {
        return (($size + $align - 1) & (~($align - 1)));
    }

    /**
     * @return CData zend_executor_globals*
     */
    protected function getExecutor(): CData
    {
        if (!\ZEND_THREAD_SAFE) {
            return $this->lib->executor_globals;
        }

        $lib = $this->lib;

        $executor = $lib->cast('char*', $lib->tsrm_get_ls_cache()) + $lib->executor_globals_offset;

        return $lib->cast('zend_executor_globals*', $executor);
    }

    /**
     * @param $data
     * @return CData zval
     */
    protected function val($data): CData
    {
        return ($this->lib->cast('zval*', $this->executor->current_execute_data) + $this->callFrameSlotSize)[0];
    }

    /**
     * @param Closure $closure
     * @return CData zend_closure
     */
    protected function closure(Closure $closure): CData
    {
        return $this->lib->cast('zend_closure*', $this->val($closure)->value->obj)[0];
    }

    /**
     * @param string|null $class_name
     */
    protected function patch(?string $class_name = null): void
    {
        $class_name = $class_name ?? SerializableClosure::class;

        if (!class_exists($class_name, true)) {
            throw new \RuntimeException("Class not found: {$class_name}");
        }

        // Get internal class table
        $class_table = $this->executor->class_table;

        $lib = $this->lib;

        // Find "parent" class
        $parent_class = $lib->zend_hash_str_find($class_table, strtolower($class_name), strlen($class_name));

        if ($parent_class === null) {
            throw new \RuntimeException("Class not found: {$class_name}");
        }

        // Find Closure class entry
        $closure_class_ce = $lib->zend_hash_str_find($class_table, 'closure', 7)->value->ce;

        // We need to copy ctor (it is replaced in do_inherit_parent_constructor() - we don't want that)
        $create_object = $closure_class_ce->create_object;
        $constructor = $closure_class_ce->constructor;

        // Apply "parent" class
        $lib->zend_do_inheritance_ex($closure_class_ce, $parent_class->value->ce, 1);

        // Restore ctor
        $closure_class_ce->create_object = $create_object;
        $closure_class_ce->constructor = $constructor;
    }

    /**
     * @return static|null
     */
    public static function instance(): ?self
    {
        // You should call init() first (probably at boot)
        return self::$instance;
    }

    /**
     * @param FFI $lib
     * @return static
     */
    public static function init(FFI $lib): self
    {
        if (!self::$instance) {
            // Register stream
            ClosureStream::register();
            // Create instance
            self::$instance = new static($lib);
        }

        return self::$instance;
    }
}