<?php declare(strict_types=1);

namespace Salient\Core\Concern;

use Salient\Container\ContainerInterface;
use Salient\Core\Facade\App;

/**
 * @api
 */
trait RequiresContainer
{
    /**
     * Get a given container or the global container, creating it if necessary
     */
    protected static function requireContainer(
        ?ContainerInterface $container = null
    ): ContainerInterface {
        return $container ?? App::getInstance();
    }
}
