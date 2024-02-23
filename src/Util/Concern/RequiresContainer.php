<?php declare(strict_types=1);

namespace Lkrms\Concern;

use Salient\Container\Container;
use Salient\Container\ContainerInterface;

trait RequiresContainer
{
    final protected static function requireContainer(?ContainerInterface $container = null): ContainerInterface
    {
        return $container ?: Container::requireGlobalContainer();
    }
}
