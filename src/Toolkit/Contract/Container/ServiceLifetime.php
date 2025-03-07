<?php declare(strict_types=1);

namespace Salient\Contract\Container;

/**
 * @api
 */
interface ServiceLifetime
{
    /**
     * One instance of the service is created if it implements
     * SingletonInterface, otherwise a new instance is always created
     */
    public const LIFETIME_INHERIT = 0;

    /**
     * A new instance of the service is always created
     */
    public const LIFETIME_TRANSIENT = 1;

    /**
     * One instance of the service is created
     */
    public const LIFETIME_SINGLETON = 2;
}
