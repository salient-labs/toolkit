<?php declare(strict_types=1);

namespace Salient\Contract\Sync;

use Salient\Core\AbstractReflectiveEnumeration;

/**
 * Sync entity data sources
 *
 * @extends AbstractReflectiveEnumeration<int>
 */
final class SyncEntitySource extends AbstractReflectiveEnumeration
{
    /**
     * Output from a successful CREATE, UPDATE or DELETE operation
     */
    public const PROVIDER_OUTPUT = 0;

    /**
     * Input to a successful CREATE, UPDATE or DELETE operation
     */
    public const OPERATION_INPUT = 1;
}
