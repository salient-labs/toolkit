<?php declare(strict_types=1);

namespace Lkrms\Concern;

use Salient\Container\Container;
use Salient\Container\ContainerInterface;
use Salient\Core\Catalog\ListConformity;
use Salient\Core\Contract\Constructible;
use Salient\Core\Contract\Extensible;
use Salient\Core\Contract\Treeable;
use Salient\Core\Introspector;
use Generator;

/**
 * Implements IConstructible to create instances from associative arrays
 *
 * @see Constructible
 */
trait TConstructible
{
    /**
     * Create an instance of the class from an array
     *
     * The constructor (if any) is invoked with parameters taken from `$data`.
     * If `$data` values remain, they are assigned to writable properties. If
     * further values remain and the class implements {@see IExtensible}, they
     * are assigned via {@see IExtensible::setMetaProperty()}.
     *
     * Array keys, constructor parameters and public property names are
     * normalised for comparison.
     *
     * @param mixed[] $data
     * @param ContainerInterface|null $container Used to create the instance if
     * set.
     * @param (Treeable&static)|null $parent If the class implements
     * {@see ITreeable}, pass `$parent` to the instance via
     * {@see ITreeable::setParent()}.
     * @return static
     */
    final public static function construct(array $data, ?ContainerInterface $container = null, $parent = null)
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
     * @param Conformity::* $conformity Use `COMPLETE` or `PARTIAL`
     * wherever possible to improve performance.
     * @param ContainerInterface|null $container Used to create each instance if
     * set.
     * @param (Treeable&static)|null $parent If the class implements
     * {@see ITreeable}, pass `$parent` to each instance via
     * {@see ITreeable::setParent()}.
     * @return Generator<static>
     */
    final public static function constructList(
        iterable $list,
        $conformity = ListConformity::NONE,
        ?ContainerInterface $container = null,
        $parent = null
    ): Generator {
        if (!$container) {
            $container = Container::requireGlobalContainer();
        }

        $closure = null;
        foreach ($list as $key => $data) {
            if (!$closure) {
                $builder = Introspector::getService($container, static::class);
                $closure =
                    in_array($conformity, [ListConformity::PARTIAL, ListConformity::COMPLETE])
                        ? $builder->getCreateFromSignatureClosure(array_keys($data), true)
                        : $builder->getCreateFromClosure(true);
            }

            yield $key => $closure($data, $container, null, $parent);
        }
    }
}
