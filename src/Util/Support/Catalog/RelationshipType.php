<?php declare(strict_types=1);

namespace Lkrms\Support\Catalog;

use Salient\Core\AbstractEnumeration;

/**
 * Entity relationship types
 *
 * @extends AbstractEnumeration<int>
 */
final class RelationshipType extends AbstractEnumeration
{
    public const ONE_TO_ONE = 0;
    public const ONE_TO_MANY = 1;
}
