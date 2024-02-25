<?php declare(strict_types=1);

namespace Salient\Sync\Catalog;

use Salient\Core\AbstractEnumeration;

/**
 * Sync entity states
 *
 * @extends AbstractEnumeration<int>
 */
final class SyncEntityState extends AbstractEnumeration
{
    /**
     * The entity is being serialized
     */
    public const SERIALIZING = 1;
}
