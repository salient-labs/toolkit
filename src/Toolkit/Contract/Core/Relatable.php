<?php declare(strict_types=1);

namespace Salient\Contract\Core;

/**
 * Has one-to-one and one-to-many relationships with other classes implementing
 * the same interface
 */
interface Relatable
{
    /**
     * Get an array that maps property names to relationships
     *
     * Example:
     *
     * ```php
     * <?php
     * public static function getRelationships(): array
     * {
     *     return [
     *         'CreatedBy' => [Cardinality::ONE_TO_ONE => User::class],
     *         'Tags' => [Cardinality::ONE_TO_MANY => Tag::class],
     *     ];
     * }
     * ```
     *
     * @return array<string,array<Cardinality::*,class-string<Relatable>>>
     * Property name => relationship type => target class
     */
    public static function getRelationships(): array;
}
