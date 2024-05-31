<?php declare(strict_types=1);

namespace Salient\Container;

use Salient\Contract\Container\ContainerInterface;

/**
 * @api
 */
trait RequiresContainer
{
    /**
     * Get the given container, optionally falling back to the global container
     * or creating a standalone instance if no container is provided
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
