<?php declare(strict_types=1);

namespace Salient\Container;

use Salient\Contract\Container\SingletonInterface;
use Salient\Core\AbstractEnumeration;

/**
 * Service lifetimes relative to the container
 *
 * @api
 *
 * @extends AbstractEnumeration<int>
 */
final class ServiceLifetime extends AbstractEnumeration
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
