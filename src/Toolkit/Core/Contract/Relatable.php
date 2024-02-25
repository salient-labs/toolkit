<?php declare(strict_types=1);

namespace Salient\Core\Contract;

use Salient\Core\Catalog\RelationshipType;

/**
 * Has one-to-one and one-to-many relationships with other classes implementing
 * the same interface
 */
interface IRelatable
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
     *         'CreatedBy' => [RelationshipType::ONE_TO_ONE => User::class],
     *         'Tags' => [RelationshipType::ONE_TO_MANY => Tag::class],
     *     ];
     * }
     * ```
     *
     * @return array<string,array<RelationshipType::*,class-string<IRelatable>>>
     * Property name => relationship type => target class
     */
    public static function getRelationships(): array;
}
