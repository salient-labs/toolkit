<?php declare(strict_types=1);

namespace Salient\Contract\Core\Entity;

use Salient\Contract\Container\ContainerInterface;
use Salient\Contract\Core\ListConformity;
use LogicException;

/**
 * @api
 */
interface Constructible
{
    /**
     * Get an instance from an array
     *
     * Values in `$data` are applied to:
     *
     * 1. Constructor parameters
     * 2. Writable properties
     * 3. Dynamic properties (if the class implements {@see Extensible})
     *
     * If the class implements {@see Normalisable}, identifiers are normalised
     * for comparison.
     *
     * If the class implements {@see Treeable} and `$parent` is given, the
     * instance is added to `$parent` as a child.
     *
     * @param mixed[] $data
     * @param static|null $parent
     * @return static
     * @throws LogicException if any values in `$data` cannot be applied to the
     * class.
     */
    public static function construct(
        array $data,
        ?object $parent = null,
        ?ContainerInterface $container = null
    );

    /**
     * Get instances from arrays
     *
     * Values in `$data` arrays are applied as per
     * {@see Constructible::construct()}.
     *
     * @template TKey of array-key
     *
     * @param iterable<TKey,mixed[]> $data
     * @param ListConformity::* $conformity
     * @param static|null $parent
     * @return iterable<TKey,static>
     * @throws LogicException if any values in `$data` arrays cannot be applied
     * to the class.
     */
    public static function constructMultiple(
        iterable $data,
        int $conformity = ListConformity::NONE,
        ?object $parent = null,
        ?ContainerInterface $container = null
    ): iterable;
}
