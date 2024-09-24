<?php declare(strict_types=1);

namespace Salient\Contract\Sync;

/**
 * @api
 */
interface EntitySource
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
