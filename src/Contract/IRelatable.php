<?php declare(strict_types=1);

namespace Lkrms\Contract;

use Lkrms\Support\Catalog\RelationshipType;

/**
 * Has one-to-one and one-to-many relationships with other classes implementing
 * the same interface
 *
 * Example:
 *
 * ```php
 * <?php
 * public const RELATIONSHIPS = [
 *     'CreatedBy' => [RelationshipType::ONE_TO_ONE => User::class],
 *     'Tags' => [RelationshipType::ONE_TO_MANY => Tag::class],
 * ];
 * ```
 */
interface IRelatable
{
    /**
     * Property name => relationship type => target class
     *
     * @var array<string,array<RelationshipType::*,class-string<IRelatable>>>
     */
    public const RELATIONSHIPS = [];
}
