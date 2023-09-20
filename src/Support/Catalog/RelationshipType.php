<?php declare(strict_types=1);

namespace Lkrms\Support\Catalog;

use Lkrms\Concept\Enumeration;

/**
 * Entity relationship types
 *
 * @extends Enumeration<int>
 */
final class RelationshipType extends Enumeration
{
    public const ONE_TO_ONE = 0;

    public const ONE_TO_MANY = 1;
}
