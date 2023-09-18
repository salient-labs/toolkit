<?php declare(strict_types=1);

namespace Lkrms\Concern;

use Lkrms\Container\Container;
use Lkrms\Contract\IContainer;
use Lkrms\Contract\IHierarchy;
use Lkrms\Support\Catalog\ArrayKeyConformity;
use Lkrms\Support\IntrospectionClass;
use Lkrms\Support\Introspector;
use Generator;

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
     * @param mixed[] $data
     * @param IContainer|null $container Used to create the instance if set.
     * @param (IHierarchy&static)|null $parent If the class implements
     * {@see IHierarchy}, pass `$parent` to the instance via
     * {@see IHierarchy::setParent()}.
     * @return static
     */
    final public static function construct(array $data, ?IContainer $container = null, $parent = null)
    {
        if (!$container) {
            $container = Container::requireGlobalContainer();
        }

        return Introspector::getService($container, static::class)
            ->getCreateFromClosure()($data, $container, null, $parent);
    }

    /**
     * Create traversable instances from traversable arrays
     *
     * See {@see TConstructible::construct()} for more information.
     *
     * @param iterable<mixed[]> $list
     * @param ArrayKeyConformity::* $conformity Use `COMPLETE` or `PARTIAL`
     *  wherever possible to improve performance.
     * @param IContainer|null $container Used to create each instance if set.
     * @param (IHierarchy&static)|null $parent If the class implements
     * {@see IHierarchy}, pass `$parent` to each instance via
     * {@see IHierarchy::setParent()}.
     * @return Generator<static>
     */
    final public static function constructList(
        iterable $list,
        int $conformity = ArrayKeyConformity::NONE,
        ?IContainer $container = null,
        $parent = null
    ): Generator {
        if (!$container) {
            $container = Container::requireGlobalContainer();
        }

        $closure = null;
        foreach ($list as $key => $data) {
            if (!$closure) {
                /** @var Introspector<static,IntrospectionClass<static>> */
                $builder = Introspector::getService($container, static::class);
                $closure =
                    in_array($conformity, [ArrayKeyConformity::PARTIAL, ArrayKeyConformity::COMPLETE])
                        ? $builder->getCreateFromSignatureClosure(array_keys($data), true)
                        : $builder->getCreateFromClosure(true);
            }

            yield $key => $closure($data, $container, null, $parent);
        }
    }
}
