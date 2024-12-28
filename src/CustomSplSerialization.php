<?php

namespace Opis\Closure;

use ArrayObject, ArrayIterator;
use SplDoublyLinkedList, SplQueue, SplStack;
use SplPriorityQueue, SplHeap, SplMinHeap, SplMaxHeap;
use SplObjectStorage;
use SplFixedArray;
use WeakMap, WeakReference;

/**
 * @internal
 */
class CustomSplSerialization
{
    public static function sArrayObject(ArrayObject $object): array
    {
        $data = [];

        if ($flags = $object->getFlags()) {
            $data["flags"] = $flags;
        }

        if (($iter = $object->getIteratorClass()) !== ArrayIterator::class) {
            $data["iter"] = $iter;
        }

        if ($vars = get_object_vars($object)) {
            $data["vars"] = (object)$vars;
        }

        if ($array = $object->getArrayCopy()) {
            $data["array"] = $array;
        }

        return $data;
    }

    public static function uArrayObject(array &$data, callable $mark): ArrayObject
    {
        $object = new ArrayObject();
        if ($data["flags"] ?? false) {
            $object->setFlags($data["flags"]);
        }
        if ($data["iter"] ?? false) {
            $object->setIteratorClass($data["iter"]);
        }

        $mark($object, $data);

        if (isset($data["vars"])) {
            foreach ($data["vars"] as $key => &$value) {
                $object->{$key} = &$value;
                unset($value);
            }
        }

        if (isset($data["array"])) {
            $object->exchangeArray($data["array"]);
        }

        return $object;
    }

    public static function sWeakMap(WeakMap $object): array
    {
        $map = [];
        foreach ($object as $key => $value) {
            $map[] = [$key, $value];
        }
        return $map;
    }

    public static function uWeakMap(array &$data, callable $mark): WeakMap
    {
        $object = new WeakMap();

        $mark($object, $data);

        foreach ($data as $item) {
            $object[$item[0]] = $item[1];
        }

        return $object;
    }

    public static function sWeakReference(WeakReference $object): array
    {
        return [$object->get()];
    }

    public static function uWeakReference(array &$data, callable $mark): WeakReference
    {
        // handle data
        $mark(null, $data);

        if (!is_object($data[0])) {
            // we create an empty weakref
            return WeakReference::create((object)[]);
        }

        return WeakReference::create($data[0]);
    }

    public static function sObjectStorage(SplObjectStorage $object): array
    {
        $map = [];
        foreach ($object as $value) {
            $map[] = [$value, $object[$value]];
        }
        return $map;
    }

    public static function uObjectStorage(array &$data, callable $mark): SplObjectStorage
    {
        $object = new SplObjectStorage();

        $mark($object, $data);

        foreach ($data as $item) {
            $object[$item[0]] = $item[1];
        }

        return $object;
    }

    public static function sFixedArray(SplFixedArray $object): array
    {
        return $object->toArray();
    }

    public static function uFixedArray(array &$data, callable $mark): SplFixedArray
    {
        $count = count($data);
        $object = new SplFixedArray($count);

        $mark($object, $data);

        for ($i = 0; $i < $count; $i++) {
            $object[$i] = $data[$i];
        }

        return $object;
    }

    public static function sDoublyLinkedList(SplDoublyLinkedList $object): array
    {
        $mode = $object->getIteratorMode();
        $object->setIteratorMode(SplDoublyLinkedList::IT_MODE_FIFO | SplDoublyLinkedList::IT_MODE_KEEP);

        $count = count($object);

        $list = [];
        for ($i = 0; $i < $count; $i++) {
            $list[] = $object[$i];
        }

        $list[] = $mode;

        $object->setIteratorMode($mode);

        return $list;
    }

    public static function uDoublyLinkedList(array &$data, callable $mark): SplDoublyLinkedList
    {
        $object = new SplDoublyLinkedList();

        $mode = array_pop($data);

        $mark($object, $data);

        $count = count($data);
        for ($i = 0; $i < $count; $i++) {
            $object->add($i, $data[$i]);
        }

        $object->setIteratorMode($mode);

        return $object;
    }

    public static function sStack(SplStack $object): array
    {
        $count = count($object);

        $list = [];
        for ($i = 0; $i < $count; $i++) {
            $list[] = $object[$i];
        }

        return array_reverse($list);
    }

    public static function uStack(array &$data, callable $mark): SplStack
    {
        $object = new SplStack();

        $mark($object, $data);

        $count = count($data);
        for ($i = 0; $i < $count; $i++) {
            $object->add($i, $data[$i]);
        }

        return $object;
    }

    public static function sQueue(SplQueue $object): array
    {
        $count = count($object);

        $list = [];
        for ($i = 0; $i < $count; $i++) {
            $list[] = $object[$i];
        }

        return $list;
    }

    public static function uQueue(array &$data, callable $mark): SplQueue
    {
        $object = new SplQueue();

        $mark($object, $data);

        $count = count($data);
        for ($i = 0; $i < $count; $i++) {
            $object->add($i, $data[$i]);
        }

        return $object;
    }

    public static function sPriorityQueue(SplPriorityQueue $object): array
    {
        $flags = $object->getExtractFlags();
        $object->setExtractFlags(SplPriorityQueue::EXTR_BOTH);
        $list = [];
        foreach ($object as $item) {
            $list[] = [$item["data"], $item["priority"]];
        }
        $list[] = $flags;
        $object->setExtractFlags($flags);

        return $list;
    }

    public static function uPriorityQueue(array &$data, callable $mark): SplPriorityQueue
    {
        $object = new SplPriorityQueue();
        $object->setExtractFlags(array_pop($data));

        $mark($object, $data);

        foreach ($data as $item) {
            $object->insert($item[0], $item[1]);
        }

        return $object;
    }


    public static function sHeap(SplHeap $object): array
    {
        return iterator_to_array($object, false);
    }

    private static function uHeap(SplHeap $object, array &$data, callable $mark): void
    {
        $mark($object, $data);
        foreach ($data as $item) {
            $object->insert($item);
        }
    }

    public static function uMinHeap(array &$data, callable $mark): SplMinHeap
    {
        $object = new SplMinHeap();
        self::uHeap($object, $data, $mark);
        return $object;
    }

    public static function uMaxHeap(array &$data, callable $mark): SplMaxHeap
    {
        $object = new SplMaxHeap();
        self::uHeap($object, $data, $mark);
        return $object;
    }
}