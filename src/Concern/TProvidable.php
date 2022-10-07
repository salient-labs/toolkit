<?php

declare(strict_types=1);

namespace Lkrms\Concern;

use Lkrms\Contract\IProvidableContext;
use Lkrms\Contract\IProvider;
use Lkrms\Support\ArrayKeyConformity;
use Lkrms\Support\ArrayMapperFlag;
use Lkrms\Support\ClosureBuilder;
use Lkrms\Support\Pipeline;
use Lkrms\Support\ProvidableContext;
use RuntimeException;

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
    private $_Provider;

    /**
     * @var string|null
     */
    private $_Providable;

    /**
     * @var IProvidableContext|null
     */
    private $_ProvidableContext;

    protected function clearProvider()
    {
        $this->_Provider = null;
    }

    public function setProvider(IProvider $provider, string $providable)
    {
        if ($this->_Provider)
        {
            throw new RuntimeException("Provider already set");
        }
        $this->_Provider   = $provider;
        $this->_Providable = $providable;

        return $this;
    }

    public function setProvidableContext(?IProvidableContext $context)
    {
        $this->_ProvidableContext = $context;

        return $this;
    }

    public function provider(): ?IProvider
    {
        return $this->_Provider;
    }

    public function providable(): ?string
    {
        return $this->_Providable;
    }

    protected function context(): ?IProvidableContext
    {
        return $this->_ProvidableContext;
    }

    /**
     * Create an instance of the class from an array on behalf of a provider
     *
     * The constructor (if any) is invoked with values from `$data`. If `$data`
     * values remain, they are assigned to writable properties. If further
     * values remain and the class implements
     * {@see \Lkrms\Contract\IExtensible}, they are assigned via
     * {@see \Lkrms\Contract\IExtensible::setMetaProperty()}.
     *
     * `$data` keys, constructor parameters and writable properties are
     * normalised for comparison.
     *
     * @return static
     */
    public static function provide(array $data, IProvider $provider, ?IProvidableContext $context = null)
    {
        $container = ($context ?: $provider)->container()->inContextOf(get_class($provider));
        $context   = $context ? $context->withContainer($container) : new ProvidableContext($container);

        return (ClosureBuilder::getBound($container, static::class)->getCreateProvidableFromClosure())($data, $provider, $context);
    }

    /**
     * Create instances of the class from arrays on behalf of a provider
     *
     * See {@see TProvidable::provide()} for more information.
     *
     * @param iterable<array> $dataList
     * @return iterable<static>
     */
    public static function provideList(iterable $dataList, IProvider $provider, int $conformity = ArrayKeyConformity::NONE, ?IProvidableContext $context = null): iterable
    {
        $container = ($context ?: $provider)->container()->inContextOf(get_class($provider));
        $context   = $context ? $context->withContainer($container) : new ProvidableContext($container);

        foreach ($dataList as $data)
        {
            if (!isset($closure))
            {
                $builder = ClosureBuilder::getBound($container, static::class);
                $closure = in_array($conformity, [ArrayKeyConformity::PARTIAL, ArrayKeyConformity::COMPLETE])
                    ? $builder->getCreateProvidableFromSignatureClosure(array_keys($data))
                    : $builder->getCreateProvidableFromClosure();
            }

            yield $closure($data, $provider, $context);
        }
    }

    #### Deprecated ####

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
     * @deprecated Use {@see TProvidable::provide()} instead
     * @psalm-param \Lkrms\Contract\IHierarchy|null $parent
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
     * {@see \Lkrms\Contract\IHierarchy}, pass `$parent` to the instance via
     * {@see \Lkrms\Contract\IHierarchy::setParent()}.
     * @return static
     */
    public static function fromProvider(IProvider $provider, array $data, callable $callback = null, array $keyMap = null, int $conformity = ArrayKeyConformity::NONE, int $flags = ArrayMapperFlag::ADD_UNMAPPED, $parent = null)
    {
        $container = $provider->container()->inContextOf(get_class($provider));
        return (Pipeline::create()
            ->send($data)
            ->if(!is_null($callback), fn(Pipeline $p) => $p->throughCallback($callback))
            ->if(!is_null($keyMap), fn(Pipeline $p)   => $p->throughKeyMap($keyMap, $conformity, $flags))
            ->then(ClosureBuilder::getBound($container, static::class)->getCreateProvidableFromClosure(), $provider, new ProvidableContext($container, $parent))
            ->run());
    }

    /**
     * Create traversable instances from traversable arrays, optionally applying
     * a callback and/or remapping each array's values before it is processed
     *
     * See {@see TProvidable::fromProvider()} for more information.
     *
     * @deprecated Use {@see TProvidable::provideList()} instead
     * @psalm-param \Lkrms\Contract\IHierarchy|null $parent
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
     * {@see \Lkrms\Contract\IHierarchy}, pass `$parent` to each instance via
     * {@see \Lkrms\Contract\IHierarchy::setParent()}.
     * @return iterable<static>
     */
    public static function listFromProvider(IProvider $provider, iterable $list, callable $callback = null, array $keyMap = null, int $conformity = ArrayKeyConformity::NONE, int $flags = ArrayMapperFlag::ADD_UNMAPPED, $parent = null): iterable
    {
        $container = $provider->container()->inContextOf(get_class($provider));
        return (Pipeline::create()
            ->stream($list)
            ->if(!is_null($callback), fn(Pipeline $p) => $p->throughCallback($callback))
            ->if(!is_null($keyMap), fn(Pipeline $p)   => $p->throughKeyMap($keyMap, $conformity, $flags))
            ->then(
                function (array $data) use (&$closure, $container, $provider, $conformity, $parent)
                {
                    if (!$closure)
                    {
                        $builder = ClosureBuilder::getBound($container, static::class);
                        $closure = in_array($conformity, [ArrayKeyConformity::PARTIAL, ArrayKeyConformity::COMPLETE])
                            ? $builder->getCreateProvidableFromSignatureClosure(array_keys($data))
                            : $builder->getCreateProvidableFromClosure();
                    }
                    return $closure($data, $provider, new ProvidableContext($container, $parent));
                }
            )->start());
    }

}
