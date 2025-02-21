<?php declare(strict_types=1);

namespace Salient\Contract\Core\Entity;

use Salient\Contract\Container\ContainerInterface;
use Salient\Contract\Core\Exception\InvalidDataException;
use Salient\Contract\HasConformity;

/**
 * @api
 */
interface Constructible extends HasConformity
{
    /**
     * Get an instance from an array
     *
     * Values in `$data` are applied to:
     *
     * 1. Constructor parameters
     * 2. Writable properties
     * 3. Declared and "magic" properties covered by {@see Writable} (if
     *    implemented by the class)
     * 4. Dynamic properties (if the class implements {@see Extensible})
     *
     * If the class implements {@see Normalisable}, array keys, parameters and
     * property names are normalised for comparison.
     *
     * Date and time values are converted to {@see DateTimeImmutable} instances
     * for parameters and declared or "magic" properties that accept
     * {@see DateTimeImmutable} or are covered by {@see Temporal} (if
     * implemented by the class).
     *
     * If the class implements {@see Treeable} and a parent is given, the
     * instance is added to the parent as a child.
     *
     * @param mixed[] $data
     * @param static|null $parent
     * @return static
     * @throws InvalidDataException if values in `$data` do not satisfy the
     * constructor or cannot be applied to the class.
     */
    public static function construct(
        array $data,
        ?object $parent = null,
        ?ContainerInterface $container = null
    );

    /**
     * Get instances from arrays
     *
     * Values in `$data` arrays are applied as per {@see construct()}.
     *
     * @template TKey of array-key
     *
     * @param iterable<TKey,mixed[]> $data
     * @param Constructible::* $conformity
     * @param static|null $parent
     * @return iterable<TKey,static>
     * @throws InvalidDataException if values in `$data` arrays do not satisfy
     * the constructor or cannot be applied to the class.
     */
    public static function constructMultiple(
        iterable $data,
        int $conformity = Constructible::CONFORMITY_NONE,
        ?object $parent = null,
        ?ContainerInterface $container = null
    ): iterable;
}
