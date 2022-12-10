<?php declare(strict_types=1);

namespace Lkrms\Concern;

use Lkrms\Container\Container;
use Lkrms\Contract\IContainer;

trait RequiresContainer
{
    final protected static function requireContainer(?IContainer $container = null): IContainer
    {
        return $container ?: Container::requireGlobalContainer();
    }
}
