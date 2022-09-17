<?php

declare(strict_types=1);

namespace Lkrms\Concern;

use Lkrms\Contract\IProvider;
use Lkrms\Support\ArrayKeyConformity;
use Lkrms\Support\ArrayMapperFlag;
use Lkrms\Support\ClosureBuilder;
use Lkrms\Support\Pipeline;
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
    public static function fromProvider(IProvider $provider, array $data, callable $callback = null, array $keyMap = null, int $conformity = ArrayKeyConformity::NONE, int $flags = ArrayMapperFlag::ADD_UNMAPPED, $parent = null)
    {
        $container = $provider->container()->inContextOf(get_class($provider));
        return (Pipeline::create()
            ->send($data)
            ->if(!is_null($callback), fn(Pipeline $p) => $p->apply($callback))
            ->if(!is_null($keyMap), fn(Pipeline $p)   => $p->map($keyMap, $conformity, $flags))
            ->then(ClosureBuilder::getBound($container, static::class)->getCreateProvidableFromClosure(), $provider, $container, $parent)
            ->run());
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
    public static function listFromProvider(IProvider $provider, iterable $list, callable $callback = null, array $keyMap = null, int $conformity = ArrayKeyConformity::NONE, int $flags = ArrayMapperFlag::ADD_UNMAPPED, $parent = null): iterable
    {
        $container = $provider->container()->inContextOf(get_class($provider));
        return (Pipeline::create()
            ->stream($list)
            ->if(!is_null($callback), fn(Pipeline $p) => $p->apply($callback))
            ->if(!is_null($keyMap), fn(Pipeline $p)   => $p->map($keyMap, $conformity, $flags))
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
                    return $closure($data, $provider, $container, $parent);
                }
            )->start());
    }

}
