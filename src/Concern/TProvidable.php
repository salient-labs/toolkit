<?php

declare(strict_types=1);

namespace Lkrms\Concern;

use Lkrms\Contract\IContainer;
use Lkrms\Contract\IProvider;
use Lkrms\Facade\Mapper;
use Lkrms\Support\ArrayKeyConformity;
use Lkrms\Support\ArrayMapperFlag;
use Lkrms\Support\ClosureBuilder;
use RuntimeException;
use UnexpectedValueException;

/**
 * Implements IProvidable to represent an external entity
 *
 * @see \Lkrms\Contract\IProvidable
 */
trait TProvidable
{
    /**
     * @var IProvider|null
     */
    private $_ProvidedBy;

    protected function clearProvider()
    {
        $this->_ProvidedBy = null;
    }

    public function setProvider(IProvider $provider): void
    {
        if ($this->_ProvidedBy)
        {
            throw new RuntimeException("Provider already set");
        }
        $this->_ProvidedBy = $provider;
    }

    public function provider(): ?IProvider
    {
        return $this->_ProvidedBy;
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
     * @param callable|null $callback If set, applied before optionally
     * remapping `$data`.
     * @param array<int|string,int|string|array<int,int|string>>|null $keyMap An
     * array that maps `$data` keys to names the class will be able to resolve.
     * See {@see \Lkrms\Support\ArrayMapper::getKeyMapClosure()} for more
     * information.
     * @param int $conformity One of the {@see ArrayKeyConformity} values. Use
     * `COMPLETE` or `PARTIAL` wherever possible to improve performance.
     * @param int $flags A bitmask of {@see \Lkrms\Support\ArrayMapperFlag}
     * values.
     * @param static|null $parent If the class implements
     * {@see \Lkrms\Contract\ITreeNode}, pass `$parent` to the instance via
     * {@see \Lkrms\Contract\ITreeNode::setParent()}.
     * @return static
     */
    public static function fromProvider(
        IProvider $provider,
        array $data,
        callable $callback = null,
        array $keyMap      = null,
        int $conformity    = ArrayKeyConformity::NONE,
        int $flags         = ArrayMapperFlag::ADD_UNMAPPED,
        $parent            = null
    ) {
        $closure = null;

        if (!is_null($keyMap))
        {
            $closure = Mapper::getKeyMapClosure($keyMap, $conformity, $flags);
        }

        if (!is_null($callback))
        {
            $closure = !$closure ? $callback : fn(array $in) => $closure($callback($in));
        }

        $container = self::maybeGetContextContainer($provider);
        return ClosureBuilder::getBound(
            $container, static::class
        )->getCreateFromClosure()($container, $provider, $data, $closure, $parent);
    }

    /**
     * Create traversable instances from traversable arrays, optionally applying
     * a callback and/or remapping each array's values before it is processed
     *
     * See {@see TProvidable::fromProvider()} for more information.
     *
     * @param iterable<array> $list
     * @param callable|null $callback If set, applied before optionally
     * remapping each array.
     * @param array<int|string,int|string|array<int,int|string>>|null $keyMap An
     * array that maps array keys to names the class will be able to resolve.
     * @param int $conformity One of the {@see ArrayKeyConformity} values. Use
     * `COMPLETE` or `PARTIAL` wherever possible to improve performance.
     * @param int $flags A bitmask of {@see \Lkrms\Support\ArrayMapperFlag}
     * values.
     * @param static|null $parent If the class implements
     * {@see \Lkrms\Contract\ITreeNode}, pass `$parent` to each instance via
     * {@see \Lkrms\Contract\ITreeNode::setParent()}.
     * @return iterable<static>
     */
    public static function listFromProvider(
        IProvider $provider,
        iterable $list,
        callable $callback = null,
        array $keyMap      = null,
        int $conformity    = ArrayKeyConformity::NONE,
        int $flags         = ArrayMapperFlag::ADD_UNMAPPED,
        $parent            = null
    ): iterable
    {
        $closure = null;

        if (!is_null($keyMap))
        {
            $closure = Mapper::getKeyMapClosure($keyMap, $conformity, $flags);
        }

        if (!is_null($callback))
        {
            $closure = !$closure ? $callback : fn(array $in) => $closure($callback($in));
        }

        $container = self::maybeGetContextContainer($provider);
        return self::getListFromProvider($container, $provider, $list, $closure, $conformity, $parent);
    }

    private static function getListFromProvider(
        IContainer $container,
        IProvider $provider,
        iterable $list,
        ? callable $closure,
        int $conformity,
        $parent
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
                if (in_array($conformity, [ArrayKeyConformity::PARTIAL, ArrayKeyConformity::COMPLETE]))
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
            yield $createFromClosure($container, $provider, $array, $closure, $parent);
        }
    }

    protected static function maybeGetContextContainer(?IProvider $provider): IContainer
    {
        if ($provider && ($container = $provider->container()) &&
            $container instanceof IContainer)
        {
            return $container->inContextOf(get_class($provider));
        }
        return $container ?? \Lkrms\Container\Container::getGlobalContainer();
    }
}
