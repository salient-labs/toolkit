<?php

declare(strict_types=1);

namespace Lkrms\Core\Mixin;

use Lkrms\Core\ClosureBuilder;
use UnexpectedValueException;

/**
 * Implements IConstructible to convert arrays to instances
 *
 * @see \Lkrms\Core\Contract\IConstructible
 */
trait TConstructible
{
    /**
     * Create an instance of the class from an array
     *
     * The constructor (if any) is invoked with parameters taken from `$data`.
     * If `$data` values remain, they are assigned to public properties. If
     * further values remain and the class implements
     * {@see \Lkrms\Core\Contract\IExtensible}, they are assigned via
     * {@see \Lkrms\Core\Contract\IExtensible::setMetaProperty()}.
     *
     * Array keys, constructor parameters and public property names are
     * normalised for comparison.
     *
     * @param array<string,mixed> $data
     * @return static
     */
    public static function fromArray(array $data)
    {
        return (ClosureBuilder::getFor(static::class)->getCreateFromClosure())($data);
    }

    /**
     * Create an instance of the class from an array after applying a callback
     *
     * See {@see TConstructible::fromArray()} for more information.
     *
     * @param array $data
     * @param callable $callback
     * @return static
     */
    public static function fromArrayVia(array $data, callable $callback)
    {
        return (ClosureBuilder::getFor(static::class)->getCreateFromClosure())($data, $callback);
    }

    /**
     * Create an instance of the class from an array after remapping its values
     *
     * See {@see ClosureBuilder::getArrayMapper()} and
     * {@see TConstructible::fromArray()} for more information.
     *
     * @param array $data
     * @param array<int|string,int|string> $keyMap An array that maps `$data`
     * keys to names the class will be able to resolve.
     * @param bool $sameKeys If `true`, improve performance by assuming `$data`
     * has the same keys in the same order as in `$keyMap`.
     * @param int $skip A bitmask of `ClosureBuilder::SKIP_*` values.
     * @return static
     */
    public static function fromMappedArray(
        array $data,
        array $keyMap,
        bool $sameKeys = false,
        int $skip      = ClosureBuilder::SKIP_MISSING | ClosureBuilder::SKIP_UNMAPPED
    ) {
        $closure = ClosureBuilder::getArrayMapper($keyMap, $sameKeys, $skip);

        return (ClosureBuilder::getFor(static::class)->getCreateFromClosure())($data, $closure);
    }

    /**
     * Create an instance of the class from an array after applying a callback
     * and remapping its values
     *
     * See {@see ClosureBuilder::getArrayMapper()} and
     * {@see TConstructible::fromArray()} for more information.
     *
     * @param array $data
     * @param callable $callback Applied before remapping `$data`.
     * @param array<int|string,int|string> $keyMap An array that maps `$data`
     * keys to names the class will be able to resolve.
     * @param bool $sameKeys If `true`, improve performance by assuming `$data`
     * has the same keys in the same order as in `$keyMap`.
     * @param int $skip A bitmask of `ClosureBuilder::SKIP_*` values.
     * @return static
     */
    public static function fromMappedArrayVia(
        array $data,
        callable $callback,
        array $keyMap,
        bool $sameKeys = false,
        int $skip      = ClosureBuilder::SKIP_MISSING
    ) {
        $closure = ClosureBuilder::getArrayMapper($keyMap, $sameKeys, $skip);
        $closure = function (array $in) use ($callback, $closure)
        {
            return $closure($callback($in));
        };

        return (ClosureBuilder::getFor(static::class)->getCreateFromClosure())($data, $closure);
    }

    /**
     * Create a list of instances from a list of arrays
     *
     * See {@see TConstructible::fromArray()} for more information.
     *
     * @param array<int,array<string,mixed>> $list
     * @param bool $sameKeys If `true`, improve performance by assuming every
     * array in the list has the same keys in the same order.
     * @return static[]
     */
    public static function listFromArrays(array $list, bool $sameKeys = false): array
    {
        return self::getListFrom($list, $sameKeys);
    }

    /**
     * Create a list of instances from a list of arrays, applying a callback
     * before each array is processed
     *
     * See {@see TConstructible::fromArray()} for more information.
     *
     * @param array[] $list
     * @param callable $callback
     * @param bool $sameKeys If `true`, improve performance by assuming every
     * array in the list has the same keys in the same order.
     * @return static[]
     */
    public static function listFromArraysVia(array $list, callable $callback, bool $sameKeys = false): array
    {
        return self::getListFrom($list, $sameKeys, $callback);
    }

    /**
     * Create a list of instances from a list of arrays, remapping each array's
     * values before it is processed
     *
     * See {@see ClosureBuilder::getArrayMapper()} and
     * {@see TConstructible::fromArray()} for more information.
     *
     * @param array[] $list
     * @param array<int|string,int|string> $keyMap An array that maps array keys
     * to names the class will be able to resolve.
     * @param bool $sameKeys If `true`, improve performance by assuming every
     * array in the list has the same keys in the same order as in `$keyMap`.
     * @param int $skip A bitmask of `ClosureBuilder::SKIP_*` values.
     * @return static[]
     */
    public static function listFromMappedArrays(
        array $list,
        array $keyMap,
        bool $sameKeys = false,
        int $skip      = ClosureBuilder::SKIP_MISSING | ClosureBuilder::SKIP_UNMAPPED
    ): array
    {
        $closure = ClosureBuilder::getArrayMapper($keyMap, $sameKeys, $skip);

        return self::getListFrom($list, $sameKeys, $closure);
    }

    /**
     * Create a list of instances from a list of arrays, applying a callback and
     * remapping each array's values before it is processed
     *
     * See {@see ClosureBuilder::getArrayMapper()} and
     * {@see TConstructible::fromArray()} for more information.
     *
     * @param array[] $list
     * @param callable $callback Applied before remapping each array.
     * @param array<int|string,int|string> $keyMap An array that maps array keys
     * to names the class will be able to resolve.
     * @param bool $sameKeys If `true`, improve performance by assuming every
     * array in the list has the same keys in the same order as in `$keyMap`.
     * @param int $skip A bitmask of `ClosureBuilder::SKIP_*` values.
     * @return static[]
     */
    public static function listFromMappedArraysVia(
        array $list,
        callable $callback,
        array $keyMap,
        bool $sameKeys = false,
        int $skip      = ClosureBuilder::SKIP_MISSING
    ): array
    {
        $closure = ClosureBuilder::getArrayMapper($keyMap, $sameKeys, $skip);
        $closure = function (array $in) use ($callback, $closure)
        {
            return $closure($callback($in));
        };

        return self::getListFrom($list, $sameKeys, $closure);
    }

    private static function getListFrom(array $arrays, bool $sameKeys, callable $closure = null): array
    {
        if (empty($arrays))
        {
            return [];
        }

        if ($sameKeys)
        {
            $first = reset($arrays);

            if ($closure)
            {
                $first = $closure($first);
            }

            $createFromClosure = ClosureBuilder::getFor(static::class)->getCreateFromSignatureClosure(array_keys($first));
        }
        else
        {
            $createFromClosure = ClosureBuilder::getFor(static::class)->getCreateFromClosure();
        }

        $list = [];

        foreach ($arrays as $index => $array)
        {
            if (!is_array($array))
            {
                throw new UnexpectedValueException("Array expected at index $index");
            }

            $list[] = ($createFromClosure)($array, $closure);
        }

        return $list;
    }
}
