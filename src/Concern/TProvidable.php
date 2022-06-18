<?php

declare(strict_types=1);

namespace Lkrms\Concern;

use Lkrms\Contract\IBindable;
use Lkrms\Contract\IProvider;
use Lkrms\Support\ClosureBuilder;
use Psr\Container\ContainerInterface as Container;
use RuntimeException;
use UnexpectedValueException;

/**
 * Implements IProvidable to represent the state of an external entity
 *
 * @see \Lkrms\Contract\IProvidable
 */
trait TProvidable
{
    /**
     * @var IProvider|null
     */
    private $_ProvidedBy;

    public function __clone()
    {
        $this->_ProvidedBy = null;
    }

    /**
     * @return static
     */
    public function setProvider(IProvider $provider)
    {
        if ($this->_ProvidedBy)
        {
            throw new RuntimeException("Provider already set");
        }
        $this->_ProvidedBy = $provider;
        return $this;
    }

    public function getProvider(): ?IProvider
    {
        return $this->_ProvidedBy;
    }

    private static function maybeInvokeInBoundContainer(IProvider $provider, callable $callback)
    {
        if ($provider instanceof IBindable)
        {
            return $provider->invokeInBoundContainer($callback);
        }
        return $callback($provider->container());
    }

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
     * @param IProvider $provider
     * @param array $data
     * @param null|callable $callback If set, applied before optionally
     * remapping `$data`.
     * @param null|array<int|string,int|string> $keyMap An array that maps
     * `$data` keys to names the class will be able to resolve. See
     * {@see ClosureBuilder::getArrayMapper()} for more information.
     * @param bool $sameKeys If `true` and `$keyMap` is set, improve performance
     * by assuming `$data` has the same keys in the same order as in `$keyMap`.
     * @param int $skip A bitmask of `ClosureBuilder::SKIP_*` values.
     * @return static
     */
    public static function fromProvider(
        IProvider $provider,
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

        return self::maybeInvokeInBoundContainer($provider,
            fn(Container $container) => (
                ClosureBuilder::getBound(
                    $container, static::class
                )->getCreateFromClosure()
            )($container, $provider, $data, $closure));
    }

    /**
     * Create traversable instances from traversable arrays, optionally applying
     * a callback and/or remapping each array's values before it is processed
     *
     * See {@see TProvidable::fromProvider()} for more information.
     *
     * @param IProvider $provider
     * @param iterable<array> $list
     * @param null|callable $callback If set, applied before optionally
     * remapping each array.
     * @param null|array<int|string,int|string> $keyMap An array that maps array
     * keys to names the class will be able to resolve.
     * @param bool $sameKeys If `true`, improve performance by assuming
     * `$keyMap` (if set) and every array being traversed have the same keys in
     * the same order.
     * @param int $skip A bitmask of `ClosureBuilder::SKIP_*` values.
     * @return iterable<static>
     */
    public static function listFromProvider(
        IProvider $provider,
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

        return self::maybeInvokeInBoundContainer($provider,
            fn(Container $container) => (
                self::getListFromProvider($container, $provider, $list, $closure, $sameKeys)
            ));
    }

    private static function getListFromProvider(
        Container $container,
        IProvider $provider,
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
            yield $createFromClosure($container, $provider, $array, $closure);
        }
    }
}
