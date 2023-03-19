<?php declare(strict_types=1);

namespace Lkrms\Concern;

use Lkrms\Container\Container;
use Lkrms\Contract\IContainer;
use Lkrms\Support\ArrayKeyConformity;
use Lkrms\Support\Introspector;

/**
 * Implements IConstructible to create instances from associative arrays
 *
 * @see \Lkrms\Contract\IConstructible
 */
trait TConstructible
{
    /**
     * Create an instance of the class from an array
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
     * @param static|null $parent If the class implements
     * {@see \Lkrms\Contract\IHierarchy}, pass `$parent` to the instance via
     * {@see \Lkrms\Contract\IHierarchy::setParent()}.
     * @return static
     */
    final public static function construct(array $data, ?IContainer $container = null, $parent = null)
    {
        if (!$container) {
            $container = Container::requireGlobalContainer();
        }

        return Introspector::getService($container, static::class)
                   ->getCreateFromClosure()($data, $container, $parent);
    }

    /**
     * Create traversable instances from traversable arrays
     *
     * See {@see TConstructible::construct()} for more information.
     *
     * @param iterable<array> $dataList
     * @param int $conformity One of the {@see ArrayKeyConformity} values. Use
     * `COMPLETE` or `PARTIAL` wherever possible to improve performance.
     * @phpstan-param ArrayKeyConformity::* $conformity
     * @param IContainer|null $container Used to create each instance if set.
     * @param static|null $parent If the class implements
     * {@see \Lkrms\Contract\IHierarchy}, pass `$parent` to each instance via
     * {@see \Lkrms\Contract\IHierarchy::setParent()}.
     * @return iterable<static>
     */
    final public static function constructList(iterable $dataList, int $conformity = ArrayKeyConformity::NONE, ?IContainer $container = null, $parent = null): iterable
    {
        if (!$container) {
            $container = Container::requireGlobalContainer();
        }

        $closure = null;
        foreach ($dataList as $data) {
            if (!$closure) {
                $builder = Introspector::getService($container, static::class);
                $closure = in_array($conformity, [ArrayKeyConformity::PARTIAL, ArrayKeyConformity::COMPLETE])
                    ? $builder->getCreateFromSignatureClosure(array_keys($data), true)
                    : $builder->getCreateFromClosure(true);
            }

            yield $closure($data, $container, $parent);
        }
    }
}
