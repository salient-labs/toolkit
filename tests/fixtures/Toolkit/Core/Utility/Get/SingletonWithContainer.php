<?php declare(strict_types=1);

namespace Salient\Tests\Core\Utility\Get;

use Lkrms\Container\Contract\HasContextualBindings;
use Lkrms\Container\Contract\HasServices;
use Lkrms\Container\Contract\SingletonInterface;
use Lkrms\Container\ContainerInterface;

class SingletonWithContainer implements HasServices, HasContextualBindings, SingletonInterface
{
    public ContainerInterface $Container;

    public function __construct(ContainerInterface $container)
    {
        $this->Container = $container;
    }

    public static function getServices(): array
    {
        return [];
    }

    public static function getContextualBindings(): array
    {
        return [];
    }
}
