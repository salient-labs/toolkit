<?php declare(strict_types=1);

namespace Lkrms\Concern;

use Lkrms\Container\Container;
use Lkrms\Container\ContainerInterface;

trait RequiresContainer
{
    final protected static function requireContainer(?ContainerInterface $container = null): ContainerInterface
    {
        return $container ?: Container::requireGlobalContainer();
    }
}
