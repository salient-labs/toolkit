<?php declare(strict_types=1);

namespace Lkrms\Container;

use Lkrms\Concept\Enumeration;
use Lkrms\Container\Contract\ServiceSingletonInterface;
use Lkrms\Container\Contract\SingletonInterface;

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
     *
     * - If the class does not implement {@see SingletonInterface} or
     *   {@see ServiceSingletonInterface}, {@see ServiceLifetime::TRANSIENT}
     *   applies.
     * - If the class implements {@see SingletonInterface} only,
     *   {@see ServiceLifetime::SINGLETON} applies.
     * - If the class implements {@see ServiceSingletonInterface} only,
     *   {@see ServiceLifetime::SERVICE_SINGLETON} applies.
     * - If the class implements {@see SingletonInterface} and
     *   {@see ServiceSingletonInterface}, {@see ServiceLifetime::SINGLETON} and
     *   {@see ServiceLifetime::SERVICE_SINGLETON} both apply.
     */
    public const INHERIT = 8;
}
