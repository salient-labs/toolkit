<?php

declare(strict_types=1);

namespace Lkrms\Core\Mixin;

use Closure;
use Lkrms\Core\Contract\IProvider;
use Lkrms\Core\Support\ClosureBuilder;
use Lkrms\Sync\Provider\SyncProvider;
use Psr\Container\ContainerInterface as Container;
use RuntimeException;
use UnexpectedValueException;

/**
 * Implements IConstructibleByProvider to convert backend data to instances
 *
 * @see \Lkrms\Core\Contract\IConstructibleByProvider
 */
trait TConstructibleByProvider
{
    /**
     * @var IProvider|null
     */
    private $ProvidedBy;

    public function __clone()
    {
        $this->ProvidedBy = null;
    }

    public function setProvider(IProvider $provider): void
    {
        if ($this->ProvidedBy)
        {
            throw new RuntimeException("Provider already set");
        }

        $this->ProvidedBy = $provider;
    }

    public function getProvider(): IProvider
    {
        return $this->ProvidedBy;
    }

    private static function maybeBindAndRun(IProvider $provider, callable $callback)
    {
        if ($provider instanceof SyncProvider)
        {
            return $provider->bindAndRun($callback);
        }
        return $callback();
    }

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
     * @param IProvider $provider
     * @param array<string,mixed> $data
     * @return static
     */
    public static function fromArray(IProvider $provider, array $data)
    {
        return self::maybeBindAndRun($provider, fn(Container $container = null) => (
            ClosureBuilder::getFor(static::class, $container)->getCreateFromClosure()
        )($provider, $data, null, $container));
    }

    /**
     * Create an instance of the class from an array after applying a callback
     *
     * See {@see TConstructibleByProvider::fromArray()} for more information.
     *
     * @param IProvider $provider
     * @param array $data
     * @param callable $callback
     * @return static
     */
    public static function fromArrayVia(IProvider $provider, array $data, callable $callback)
    {
        return self::maybeBindAndRun($provider, fn(Container $container = null) => (
            ClosureBuilder::getFor(static::class, $container)->getCreateFromClosure()
        )($provider, $data, $callback, $container));
    }

    /**
     * Create an instance of the class from an array after remapping its values
     *
     * See {@see ClosureBuilder::getArrayMapper()} and
     * {@see TConstructibleByProvider::fromArray()} for more information.
     *
     * @param IProvider $provider
     * @param array $data
     * @param array<int|string,int|string> $keyMap An array that maps `$data`
     * keys to names the class will be able to resolve.
     * @param bool $sameKeys If `true`, improve performance by assuming `$data`
     * has the same keys in the same order as in `$keyMap`.
     * @param int $skip A bitmask of `ClosureBuilder::SKIP_*` values.
     * @return static
     */
    public static function fromMappedArray(
        IProvider $provider,
        array $data,
        array $keyMap,
        bool $sameKeys = false,
        int $skip      = ClosureBuilder::SKIP_MISSING | ClosureBuilder::SKIP_UNMAPPED
    ) {
        $closure = ClosureBuilder::getArrayMapper($keyMap, $sameKeys, $skip);

        return self::maybeBindAndRun($provider, fn(Container $container = null) => (
            ClosureBuilder::getFor(static::class, $container)->getCreateFromClosure()
        )($provider, $data, $closure, $container));
    }

    /**
     * Create an instance of the class from an array after applying a callback
     * and remapping its values
     *
     * See {@see ClosureBuilder::getArrayMapper()} and
     * {@see TConstructibleByProvider::fromArray()} for more information.
     *
     * @param IProvider $provider
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
        IProvider $provider,
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

        return self::maybeBindAndRun($provider, fn(Container $container = null) => (
            ClosureBuilder::getFor(static::class, $container)->getCreateFromClosure()
        )($provider, $data, $closure, $container));
    }

    /**
     * Create a list of instances from a list of arrays
     *
     * See {@see TConstructibleByProvider::fromArray()} for more information.
     *
     * @param IProvider $provider
     * @param iterable<array<string,mixed>> $list
     * @param bool $sameKeys If `true`, improve performance by assuming every
     * array in the list has the same keys in the same order.
     * @return iterable<static>
     */
    public static function listFromArrays(
        IProvider $provider,
        iterable $list,
        bool $sameKeys = false
    ): iterable
    {
        return self::maybeBindAndRun($provider,
            fn(Container $container = null) => (
                self::getListFrom($provider, $list, $sameKeys, $container)
            ));
    }

    /**
     * Create a list of instances from a list of arrays, applying a callback
     * before each array is processed
     *
     * See {@see TConstructibleByProvider::fromArray()} for more information.
     *
     * @param IProvider $provider
     * @param iterable<array> $list
     * @param callable $callback
     * @param bool $sameKeys If `true`, improve performance by assuming every
     * array in the list has the same keys in the same order.
     * @return iterable<static>
     */
    public static function listFromArraysVia(
        IProvider $provider,
        iterable $list,
        callable $callback,
        bool $sameKeys = false
    ): iterable
    {
        return self::maybeBindAndRun($provider,
            fn(Container $container = null) => (
                self::getListFrom($provider, $list, $sameKeys, $container, $callback)
            ));
    }

    /**
     * Create a list of instances from a list of arrays, remapping each array's
     * values before it is processed
     *
     * See {@see ClosureBuilder::getArrayMapper()} and
     * {@see TConstructibleByProvider::fromArray()} for more information.
     *
     * @param IProvider $provider
     * @param iterable<array> $list
     * @param array<int|string,int|string> $keyMap An array that maps array keys
     * to names the class will be able to resolve.
     * @param bool $sameKeys If `true`, improve performance by assuming every
     * array in the list has the same keys in the same order as in `$keyMap`.
     * @param int $skip A bitmask of `ClosureBuilder::SKIP_*` values.
     * @return iterable<static>
     */
    public static function listFromMappedArrays(
        IProvider $provider,
        iterable $list,
        array $keyMap,
        bool $sameKeys = false,
        int $skip      = ClosureBuilder::SKIP_MISSING | ClosureBuilder::SKIP_UNMAPPED
    ): iterable
    {
        $closure = ClosureBuilder::getArrayMapper($keyMap, $sameKeys, $skip);

        return self::maybeBindAndRun($provider,
            fn(Container $container = null) => (
                self::getListFrom($provider, $list, $sameKeys, $container, $closure)
            ));
    }

    /**
     * Create a list of instances from a list of arrays, applying a callback and
     * remapping each array's values before it is processed
     *
     * See {@see ClosureBuilder::getArrayMapper()} and
     * {@see TConstructibleByProvider::fromArray()} for more information.
     *
     * @param IProvider $provider
     * @param iterable<array> $list
     * @param callable $callback Applied before remapping each array.
     * @param array<int|string,int|string> $keyMap An array that maps array keys
     * to names the class will be able to resolve.
     * @param bool $sameKeys If `true`, improve performance by assuming every
     * array in the list has the same keys in the same order as in `$keyMap`.
     * @param int $skip A bitmask of `ClosureBuilder::SKIP_*` values.
     * @return iterable<static>
     */
    public static function listFromMappedArraysVia(
        IProvider $provider,
        iterable $list,
        callable $callback,
        array $keyMap,
        bool $sameKeys = false,
        int $skip      = ClosureBuilder::SKIP_MISSING
    ): iterable
    {
        $closure = ClosureBuilder::getArrayMapper($keyMap, $sameKeys, $skip);
        $closure = function (array $in) use ($callback, $closure)
        {
            return $closure($callback($in));
        };

        return self::maybeBindAndRun($provider,
            fn(Container $container = null) => (
                self::getListFrom($provider, $list, $sameKeys, $container, $closure)
            ));
    }

    private static function getListClosureFrom(
        array $array,
        bool $sameKeys,
        ?Container $container,
        ? callable $closure
    ): Closure
    {
        if (!$sameKeys)
        {
            return ClosureBuilder::getFor(static::class, $container)->getCreateFromClosure();
        }
        if ($closure)
        {
            $array = $closure($array);
        }

        return ClosureBuilder::getFor(static::class, $container)->getCreateFromSignatureClosure(array_keys($array));
    }

    private static function getListFrom(
        IProvider $provider,
        iterable $arrays,
        bool $sameKeys,
        ?Container $container,
        callable $closure = null
    ): iterable
    {
        foreach ($arrays as $index => $array)
        {
            if (!is_array($array))
            {
                throw new UnexpectedValueException("Array expected at index $index");
            }

            yield ($createFromClosure ??
                $createFromClosure = self::getListClosureFrom(
                    $array, $sameKeys, $container, $closure
                ))($provider, $array, $closure, $container);
        }
    }
}
