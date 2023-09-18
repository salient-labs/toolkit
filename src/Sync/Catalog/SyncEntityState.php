<?php declare(strict_types=1);

namespace Lkrms\Sync\Catalog;

use Lkrms\Concept\Enumeration;

/**
 * Sync entity states
 *
 * @extends Enumeration<int>
 */
final class SyncEntityState extends Enumeration
{
    /**
     * The entity is being serialized
     */
    public const SERIALIZING = 1;
}
