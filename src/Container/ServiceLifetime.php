<?php declare(strict_types=1);

namespace Lkrms\Container;

use Lkrms\Concept\Enumeration;

/**
 * Service lifetimes relative to the container
 *
 * @extends Enumeration<int>
 */
final class ServiceLifetime extends Enumeration
{
    /**
     * A new instance of the class is always created
     */
    public const TRANSIENT = 1;

    /**
     * One instance of the class is created
     */
    public const SINGLETON = 2;

    /**
     * An instance of the class is created for each service it provides
     */
    public const SERVICE_SINGLETON = 4;

    /**
     * Service interfaces implemented by the class are honoured
     *
     * Specifically:
     * - If the class only implements {@see \Lkrms\Contract\IService},
     *   {@see ServiceLifetime::TRANSIENT} applies
     * - {@see \Lkrms\Contract\IServiceSingleton} and
     *   {@see \Lkrms\Contract\IServiceShared} correspond to
     *   {@see ServiceLifetime::SINGLETON} and
     *   {@see ServiceLifetime::SERVICE_SINGLETON} respectively.
     * - Implementing {@see \Lkrms\Contract\IServiceSingleton} AND
     *   {@see \Lkrms\Contract\IServiceShared} is equivalent to:
     *   ```php
     *   $lifetime = ServiceLifetime::SERVICE_SINGLETON | ServiceLifetime::SINGLETON
     *   ```
     */
    public const INHERIT = 8;
}
