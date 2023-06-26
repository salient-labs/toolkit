<?php declare(strict_types=1);

namespace Lkrms\Sync\Catalog;

use Lkrms\Concept\ConvertibleEnumeration;

/**
 * Sync entity data sources
 *
 * @extends ConvertibleEnumeration<int>
 */
final class SyncEntitySource extends ConvertibleEnumeration
{
    /**
     * An HTTP GET response
     */
    public const HTTP_READ = 0;

    /**
     * An HTTP POST, PUT, PATCH or DELETE response
     */
    public const HTTP_WRITE = 1;

    /**
     * The input to a CREATE, UPDATE or DELETE operation
     */
    public const SYNC_OPERATION = 2;
}
