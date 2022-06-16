<?php

declare(strict_types=1);

namespace Lkrms\Concern;

use Lkrms\Support\ClosureBuilder;
use Psr\Container\ContainerInterface as Container;
use UnexpectedValueException;

/**
 * Implements IConstructible to convert arrays to instances
 *
 * @see \Lkrms\Contract\IConstructible
 */
trait TConstructible
{
    /**
     * Create an instance of the class from an array, optionally applying a
     * callback and/or remapping its values
     *
     * The constructor (if any) is invoked with parameters taken from `$data`.
     * If `$data` values remain, they are assigned to writable properties. If
     * further values remain and the class implements
     * {@see \Lkrms\Contract\IExtensible}, they are assigned via
     * {@see \Lkrms\Contract\IExtensible::setMetaProperty()}.
     *
     * Array keys, constructor parameters and public property names are
     * normalised for comparison.
     *
     * @param null|Container $container Used to create the instance if set.
     * @param array $data
     * @param callable|null $callback If set, applied before optionally
     * remapping `$data`.
     * @param array<int|string,int|string>|null $keyMap An array that maps
     * `$data` keys to names the class will be able to resolve. See
     * {@see ClosureBuilder::getArrayMapper()} for more information.
     * @param bool $sameKeys If `true` and `$keyMap` is set, improve performance
     * by assuming `$data` has the same keys in the same order as in `$keyMap`.
     * @param int $skip A bitmask of `ClosureBuilder::SKIP_*` values.
     * @return static
     */
    public static function from(
        ?Container $container,
        array $data,
        callable $callback = null,
        array $keyMap      = null,
        bool $sameKeys     = false,
        int $skip          = ClosureBuilder::SKIP_MISSING
    ) {
        $closure = null;

        if (!is_null($keyMap))
        {
            $closure = ClosureBuilder::getArrayMapper($keyMap, $sameKeys, $skip);
        }

        if (!is_null($callback))
        {
            $closure = !$closure ? $callback : fn(array $in) => $closure($callback($in));
        }

        return (ClosureBuilder::getBound(
            $container, static::class
        )->getCreateFromClosure())($data, $closure, $container);
    }

    /**
     * Create traversable instances from traversable arrays, optionally applying
     * a callback and/or remapping each array's values before it is processed
     *
     * See {@see TConstructible::from()} for more information.
     *
     * @param null|Container $container Used to create each instance if set.
     * @param iterable<array> $list
     * @param callable|null $callback If set, applied before optionally
     * remapping each array.
     * @param array<int|string,int|string>|null $keyMap An array that maps array
     * keys to names the class will be able to resolve.
     * @param bool $sameKeys If `true`, improve performance by assuming
     * `$keyMap` (if set) and every array being traversed have the same keys in
     * the same order.
     * @param int $skip A bitmask of `ClosureBuilder::SKIP_*` values.
     * @return iterable<static>
     */
    public static function listFrom(
        ?Container $container,
        iterable $list,
        callable $callback = null,
        array $keyMap      = null,
        bool $sameKeys     = false,
        int $skip          = ClosureBuilder::SKIP_MISSING
    ): iterable
    {
        $closure = null;

        if (!is_null($keyMap))
        {
            $closure = ClosureBuilder::getArrayMapper($keyMap, $sameKeys, $skip);
        }

        if (!is_null($callback))
        {
            $closure = !$closure ? $callback : fn(array $in) => $closure($callback($in));
        }

        return self::getListFrom($container, $list, $closure, $sameKeys);
    }

    private static function getListFrom(
        ?Container $container,
        iterable $list,
        ? callable $closure,
        bool $sameKeys
    ): iterable
    {
        $createFromClosure = null;
        foreach ($list as $index => $array)
        {
            if (!is_array($array))
            {
                throw new UnexpectedValueException("Array expected at index $index");
            }
            if (!$createFromClosure)
            {
                if ($sameKeys)
                {
                    if ($closure)
                    {
                        $closureArray = $closure($array);
                    }
                    $createFromClosure = ClosureBuilder::getBound(
                        $container, static::class
                    )->getCreateFromSignatureClosure(array_keys($closureArray ?? $array));
                }
                else
                {
                    $createFromClosure = ClosureBuilder::getBound(
                        $container, static::class
                    )->getCreateFromClosure();
                }
            }
            yield $createFromClosure($array, $closure, $container);
        }
    }

    /**
     * @deprecated Use {@see TConstructible::from()} instead
     * @return static
     */
    public static function fromArray(array $data)
    {
        return self::from(null, $data);
    }

    /**
     * @deprecated Use {@see TConstructible::from()} instead
     * @return static
     */
    public static function fromArrayVia(array $data, callable $callback)
    {
        return self::from(null, $data, $callback);
    }

    /**
     * @deprecated Use {@see TConstructible::from()} instead
     * @return static
     */
    public static function fromMappedArray(array $data, array $keyMap, bool $sameKeys = false, int $skip = ClosureBuilder::SKIP_MISSING | ClosureBuilder::SKIP_UNMAPPED)
    {
        return self::from(null, $data, null, $keyMap, $sameKeys, $skip);
    }

    /**
     * @deprecated Use {@see TConstructible::from()} instead
     * @return static
     */
    public static function fromMappedArrayVia(array $data, callable $callback, array $keyMap, bool $sameKeys = false, int $skip = ClosureBuilder::SKIP_MISSING)
    {
        return self::from(null, $data, $callback, $keyMap, $sameKeys, $skip);
    }

    /**
     * @deprecated Use {@see TConstructible::listFrom()} instead
     * @return iterable<static>
     */
    public static function listFromArrays(iterable $list, bool $sameKeys = false): iterable
    {
        return self::listFrom(null, $list, null, null, $sameKeys);
    }

    /**
     * @deprecated Use {@see TConstructible::listFrom()} instead
     * @return iterable<static>
     */
    public static function listFromArraysVia(iterable $list, callable $callback, bool $sameKeys = false): iterable
    {
        return self::listFrom(null, $list, $callback, null, $sameKeys);
    }

    /**
     * @deprecated Use {@see TConstructible::listFrom()} instead
     * @return iterable<static>
     */
    public static function listFromMappedArrays(iterable $list, array $keyMap, bool $sameKeys = false, int $skip = ClosureBuilder::SKIP_MISSING | ClosureBuilder::SKIP_UNMAPPED): iterable
    {
        return self::listFrom(null, $list, null, $keyMap, $sameKeys, $skip);
    }

    /**
     * @deprecated Use {@see TConstructible::listFrom()} instead
     * @return iterable<static>
     */
    public static function listFromMappedArraysVia(iterable $list, callable $callback, array $keyMap, bool $sameKeys = false, int $skip = ClosureBuilder::SKIP_MISSING): iterable
    {
        return self::listFrom(null, $list, $callback, $keyMap, $sameKeys, $skip);
    }
}
