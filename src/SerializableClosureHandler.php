<?php
/* ===========================================================================
 * Copyright (c) 2020 Zindex Software
 *
 * Licensed under the MIT License
 * =========================================================================== */

namespace Opis\Closure;

use FFI, Closure;
use FFI\{CData, CType, Exception as FFIException};

class SerializableClosureHandler
{
    /**
     * @var SerializableClosureHandler|null
     */
    protected static ?SerializableClosureHandler $instance = null;

    /**
     * FFI SCOPE
     */
    protected const SCOPE_NAME = 'OpisClosure';

    /**
     * WIN detector
     */
    protected const IS_WIN = \DIRECTORY_SEPARATOR === '\\';

    /**
     * Preprocess regex
     */
    protected const PREPROCESS_REGEX = '/^\s*#(?<if>ifn?def)\s+(?<cond>.+?)\s*(?<then>^.+?)(?:^\s*#else\s*(?<else>^.+?))?^\s*#endif\s*/sm';

    /**
     * @var null|FFI
     */
    protected ?FFI $lib = null;

    /**
     * @var CData|null
     */
    protected ?CData $executor = null;

    /**
     * @var int|null
     */
    protected ?int $slotSize = null;

    /**
     * SerializableClosureHandler constructor.
     * @param bool $preload
     */
    final protected function __construct(bool $preload)
    {
        // Load php library
        try {
            $this->lib = FFI::scope(self::SCOPE_NAME);
        } catch (FFIException $ex) {
            $this->lib = $this->load($preload);
        }

        // Apply patch
        $this->patch();
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
     * @return CData zend_executor_globals*
     */
    protected function executor(): CData
    {
        if ($this->executor !== null) {
            return $this->executor;
        }

        if (!\ZEND_THREAD_SAFE) {
            return $this->executor = $this->lib->executor_globals;
        }

        $lib = $this->lib;

        $executor = $lib->cast('char*', $lib->tsrm_get_ls_cache()) + $lib->executor_globals_offset;

        return $this->executor = $lib->cast('zend_executor_globals*', $executor);
    }

    /**
     * @param $data
     * @return CData zval
     */
    protected function val($data): CData
    {
        return ($this->lib->cast('zval*', $this->executor()->current_execute_data) + $this->callFrameSlot())[0];
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
     * ZEND_CALL_FRAME_SLOT
     * @return int
     */
    protected function callFrameSlot(): int
    {
        if ($this->slotSize === null) {
            $lib = $this->lib;
            $zed = $this->alignedSize(FFI::sizeof($lib->type('zend_execute_data')));
            $zval = $this->alignedSize(FFI::sizeof($lib->type('zval')));
            $this->slotSize = intdiv($zed + $zval - 1, $zval);
        }

        return $this->slotSize;
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
     * @param string|null $class_name
     */
    protected function patch(?string $class_name = null): void
    {
        $class_name = $class_name ?? SerializableClosure::class;

        if (!class_exists($class_name, true)) {
            throw new \RuntimeException("Class not found: {$class_name}");
        }

        // Get internal class table
        $class_table = $this->executor()->class_table;

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
     * @return string Location to header file
     */
    protected function headerFile(): string
    {
        return __DIR__ . '/../include/headers.h';
    }

    /**
     * @return string
     */
    protected function libName(): string
    {
        if (self::IS_WIN) {
            return 'php' . \PHP_MAJOR_VERSION . (\PHP_ZTS ? 'ts' : '') . (\PHP_DEBUG ? '_debug' : '') . '.dll';
        }

        return '';
    }

    /**
     * @return array Definitions
     */
    protected function defs(): array
    {
        $defs = [
            'ZEND_API' => '__declspec(dllimport)',
            'ZEND_FASTCALL' => self::IS_WIN ? '__vectorcall' : '',
            'ZEND_MAX_RESERVED_RESOURCES' => 6,
        ];

        if (\ZEND_THREAD_SAFE) {
            $defs['ZTS'] = 1;
        }

        if (self::IS_WIN) {
            $defs['ZEND_WIN32'] = 1;
        }

        if (\PHP_INT_SIZE === 8) {
            $defs['PLATFORM_64'] = 1;
        } else {
            $defs['PLATFORM_32'] = 1;
        }

        return $defs;
    }

    /**
     * @param string $data Unprocessed content
     * @param array $defs Definitions
     * @return string Processed content
     */
    private function preprocess(string $data, array $defs = []): string
    {
        $data = preg_replace_callback(self::PREPROCESS_REGEX, function (array $m) use (&$defs) {
            $ok = array_key_exists($m['cond'], $defs);
            if ($m['if'] === 'ifndef') {
                $ok = !$ok;
            }
            if ($ok) {
                return $m['then'];
            }
            return $m['else'] ?? '';
        }, $data);

        $data = strtr($data, $defs);

        return $data;
    }

    /**
     * @param bool $preload
     * @return FFI
     */
    private function load(bool $preload): FFI
    {
        $lib = $this->libName();

        $data = file_get_contents($this->headerFile());
        $data = $this->preprocess($data, $this->defs() + [
                'FFI_SCOPE_NAME' => self::SCOPE_NAME,
                'FFI_LIB_NAME' => $lib,
            ]);

        if ($preload) {
            $file = tempnam(sys_get_temp_dir(), 'opis_closure_ffi_');
            file_put_contents($file, $data);
            unset($data);

            $ffi = FFI::load($file);

            unlink($file);

            return $ffi;
        }

        if ($lib) {
            return FFI::cdef($data, $lib);
        }

        return FFI::cdef($data);
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
     * @param bool $preload
     * @return static
     */
    public static function init(bool $preload = false): self
    {
        if (!self::$instance) {
            ClosureStream::register();
            // Create instance
            self::$instance = new static($preload);
        }

        return self::$instance;
    }
}