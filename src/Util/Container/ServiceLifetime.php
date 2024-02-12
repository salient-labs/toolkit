<?php declare(strict_types=1);

namespace Lkrms\Container;

use Lkrms\Concept\Enumeration;
use Lkrms\Container\Contract\SingletonInterface;

/**
 * Service lifetimes relative to the container
 *
 * @api
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
     * Service lifetime interfaces inherited by the class are honoured
     *
     * Specifically:
     *
     * - If the class does not implement {@see SingletonInterface},
     *   {@see ServiceLifetime::TRANSIENT} applies.
     * - If the class implements {@see SingletonInterface},
     *   {@see ServiceLifetime::SINGLETON} applies.
     */
    public const INHERIT = 3;
}
