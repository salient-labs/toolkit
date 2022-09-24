<?php

declare(strict_types=1);

namespace Lkrms\Concern;

use Lkrms\Contract\IContainer;
use Lkrms\Support\ArrayKeyConformity;
use Lkrms\Support\ArrayMapperFlag;
use Lkrms\Support\ClosureBuilder;
use Lkrms\Support\Pipeline;

/**
 * Implements IConstructible to create instances of itself from arrays
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
     * @param IContainer|null $container Used to create the instance if set.
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
    public static function from(?IContainer $container, array $data, ? callable $callback = null, ?array $keyMap = null, int $conformity = ArrayKeyConformity::NONE, int $flags = ArrayMapperFlag::ADD_UNMAPPED, $parent = null)
    {
        return (Pipeline::create()
            ->send($data)
            ->if(!is_null($callback), fn(Pipeline $p) => $p->throughCallback($callback))
            ->if(!is_null($keyMap), fn(Pipeline $p)   => $p->throughKeyMap($keyMap, $conformity, $flags))
            ->then(ClosureBuilder::maybeGetBound($container, static::class)->getCreateFromClosure(), $container, $parent)
            ->run());
    }

    /**
     * Create traversable instances from traversable arrays, optionally applying
     * a callback and/or remapping each array's values before it is processed
     *
     * See {@see TConstructible::from()} for more information.
     *
     * @param IContainer|null $container Used to create each instance if set.
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
    public static function listFrom(?IContainer $container, iterable $list, ? callable $callback = null, ?array $keyMap = null, int $conformity = ArrayKeyConformity::NONE, int $flags = ArrayMapperFlag::ADD_UNMAPPED, $parent = null): iterable
    {
        return (Pipeline::create()
            ->stream($list)
            ->if(!is_null($callback), fn(Pipeline $p) => $p->throughCallback($callback))
            ->if(!is_null($keyMap), fn(Pipeline $p)   => $p->throughKeyMap($keyMap, $conformity, $flags))
            ->then(
                function (array $data) use (&$closure, $container, $conformity, $parent)
                {
                    if (!$closure)
                    {
                        $builder = ClosureBuilder::maybeGetBound($container, static::class);
                        $closure = in_array($conformity, [ArrayKeyConformity::PARTIAL, ArrayKeyConformity::COMPLETE])
                            ? $builder->getCreateFromSignatureClosure(array_keys($data))
                            : $builder->getCreateFromClosure();
                    }
                    return $closure($data, $container, $parent);
                }
            )->start());
    }

}
