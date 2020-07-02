<?php
/* ===========================================================================
 * Copyright 2020 Zindex Software
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *    http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 * ============================================================================ */

namespace Opis\Closure;

use Closure, FFI, FFI\CData, RuntimeException;
use const ZEND_THREAD_SAFE;

/**
 * @internal
 */
final class SerializableClosureHandler
{
    /**
     * @var static|null
     */
    private static ?SerializableClosureHandler $instance = null;

    /**
     * @var FFI
     */
    private FFI $lib;

    /**
     * @var CData
     */
    private CData $executor;

    /**
     * @var int
     */
    private int $callFrameSlotSize;

    /**
     * @var CData|null
     */
    private ?CData $patchedClosureClassEntry = null;

    /**
     * @param FFI $lib
     */
    private function __construct(FFI $lib)
    {
        // Set lib
        $this->lib = $lib;

        // Calculate call frame slot size
        $this->callFrameSlotSize = $this->getCallFrameSlotSize();

        // Get executor
        $this->executor = $this->getExecutor();

        // Apply patch
        $this->patch();
    }

    /**
     * @param Closure $closure
     * @return array
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

        $ret['code'] = $reflector->getCodeWrapper();

        if ($use = $reflector->getUseVariables()) {
            $ret['use'] = $use;
        }

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
            throw new RuntimeException("Invalid data");
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
    private function getCallFrameSlotSize(): int
    {
        $zed_type = $this->lib->type('zend_execute_data');
        $zed = $this->alignedSize(FFI::sizeof($zed_type));

        $zval_type = $this->lib->type('zval');
        $zval = $this->alignedSize(FFI::sizeof($zval_type));

        return intdiv($zed + $zval - 1, $zval);
    }

    /**
     * ZEND_MM_ALIGNED_SIZE
     * @param int $size
     * @param int $align
     * @return int
     */
    private function alignedSize(int $size, int $align = 8): int
    {
        return (($size + $align - 1) & (~($align - 1)));
    }

    /**
     * @return CData zend_executor_globals*
     */
    private function getExecutor(): CData
    {
        if (!ZEND_THREAD_SAFE) {
            return $this->lib->executor_globals;
        }

        $lib = $this->lib;

        $executor = $lib->cast('char*', $lib->tsrm_get_ls_cache()) + $lib->executor_globals_offset;

        return $lib->cast('zend_executor_globals*', $executor);
    }

    /**
     * @param mixed $data This must be kept!
     * @return CData zval
     * @noinspection PhpUnusedParameterInspection
     */
    private function val($data): CData
    {
        return ($this->lib->cast('zval*', $this->executor->current_execute_data) + $this->callFrameSlotSize)[0];
    }

    /**
     * @param Closure $closure
     * @return CData zend_closure
     */
    private function closure(Closure $closure): CData
    {
        return $this->lib->cast('zend_closure*', $this->val($closure)->value->obj)[0];
    }

    private function patch(): void
    {
        if (class_parents(Closure::class)) {
            // Patch already applied
            return;
        }

        $class_name = SerializableClosure::class;

        // Autoload class
        if (!class_exists($class_name, true)) {
            throw new RuntimeException("Class not found: {$class_name}");
        }

        // Get internal class table
        $class_table = $this->executor->class_table;

        $lib = $this->lib;

        // Find "parent" class
        $parent_class = $lib->zend_hash_str_find($class_table, strtolower($class_name), strlen($class_name));

        if ($parent_class === null) {
            throw new RuntimeException("Class not found: {$class_name}");
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

        // Check if cli mode
        if (PHP_SAPI === 'cli') {
            $this->patchedClosureClassEntry = $closure_class_ce;
        }
    }

    /**
     * Destructor
     */
    public function __destruct()
    {
        if (is_null($this->patchedClosureClassEntry)) {
            return;
        }

        // We need to remove non-internal methods added by our patch()

        $lib = $this->lib;
        $ft = FFI::addr($this->patchedClosureClassEntry->function_table);

        foreach (get_class_methods(SerializableClosure::class) as $method) {
            $lib->zend_hash_str_del($ft, $method, strlen($method));
        }

        $this->patchedClosureClassEntry = null;
    }

    /**
     * @return self|null
     */
    public static function instance(): ?self
    {
        // You should call init() first (probably at boot)
        return self::$instance;
    }

    /**
     * @param FFI $lib
     * @return self
     */
    public static function init(FFI $lib): self
    {
        if (!self::$instance) {
            // Register stream
            ClosureStream::register();
            // Create instance
            self::$instance = new self($lib);
        }

        return self::$instance;
    }
}