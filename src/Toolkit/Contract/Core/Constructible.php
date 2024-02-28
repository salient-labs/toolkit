<?php declare(strict_types=1);

namespace Salient\Core\Contract;

use Salient\Container\ContainerInterface;
use Salient\Core\Catalog\ListConformity;

/**
 * @api
 */
interface Constructible
{
    /**
     * Create an instance of the class from an array
     *
     * If the class has a constructor, values are passed from `$data` to its
     * parameters. If values remain, they are assigned to writable properties.
     * If further values remain and the class implements {@see Extensible}, they
     * are assigned via {@see Extensible::setMetaProperty()}, otherwise an
     * exception is thrown.
     *
     * Array keys, constructor parameters and property names are normalised for
     * comparison if the class implements {@see Normalisable} or
     * {@see NormaliserFactory}.
     *
     * If the class implements {@see Treeable} and `$parent` is set, it is
     * passed to the instance via {@see Treeable::setParent()}.
     *
     * @param mixed[] $data
     * @param (Treeable&static)|null $parent
     * @return static
     */
    public static function construct(
        array $data,
        ?ContainerInterface $container = null,
        $parent = null
    );

    /**
     * Create instances of the class from arrays
     *
     * See {@see Constructible::construct()} for more information.
     *
     * @template TKey of array-key
     *
     * @param iterable<TKey,mixed[]> $list
     * @param ListConformity::* $conformity Use {@see ListConformity::COMPLETE}
     * or {@see ListConformity::PARTIAL} wherever possible to improve
     * performance.
     * @param (Treeable&static)|null $parent
     * @return iterable<TKey,static>
     */
    public static function constructList(
        iterable $list,
        $conformity = ListConformity::NONE,
        ?ContainerInterface $container = null,
        $parent = null
    ): iterable;
}
