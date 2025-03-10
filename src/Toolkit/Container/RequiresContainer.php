<?php declare(strict_types=1);

namespace Salient\Container;

use Salient\Contract\Container\ContainerInterface;

/**
 * @api
 */
trait RequiresContainer
{
    /**
     * In order of preference, get the given container, the global container or
     * a standalone container
     *
     * @param bool $getGlobalContainer If `true` and `$container` is `null`,
     * return the global container if it is set
     * @param bool $createGlobalContainer If `true` and `$container` is `null`,
     * create the global container if it is not set
     */
    protected static function requireContainer(
        ?ContainerInterface $container = null,
        bool $getGlobalContainer = true,
        bool $createGlobalContainer = false
    ): ContainerInterface {
        return $container
            ?? ($createGlobalContainer || ($getGlobalContainer && Container::hasGlobalContainer())
                ? Container::getGlobalContainer()
                : new Container());
    }
}
