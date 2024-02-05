<?php declare(strict_types=1);

namespace Lkrms\Concern;

use Lkrms\Container\Container;
use Lkrms\Container\ContainerInterface;
use Lkrms\Contract\IConstructible;
use Lkrms\Contract\IExtensible;
use Lkrms\Contract\ITreeable;
use Lkrms\Support\Catalog\ArrayKeyConformity;
use Lkrms\Support\Introspector;
use Generator;

/**
 * Implements IConstructible to create instances from associative arrays
 *
 * @see IConstructible
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
     * @param (ITreeable&static)|null $parent If the class implements
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
     * @param ArrayKeyConformity::* $conformity Use `COMPLETE` or `PARTIAL`
     * wherever possible to improve performance.
     * @param ContainerInterface|null $container Used to create each instance if
     * set.
     * @param (ITreeable&static)|null $parent If the class implements
     * {@see ITreeable}, pass `$parent` to each instance via
     * {@see ITreeable::setParent()}.
     * @return Generator<static>
     */
    final public static function constructList(
        iterable $list,
        $conformity = ArrayKeyConformity::NONE,
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
                    in_array($conformity, [ArrayKeyConformity::PARTIAL, ArrayKeyConformity::COMPLETE])
                        ? $builder->getCreateFromSignatureClosure(array_keys($data), true)
                        : $builder->getCreateFromClosure(true);
            }

            yield $key => $closure($data, $container, null, $parent);
        }
    }
}
