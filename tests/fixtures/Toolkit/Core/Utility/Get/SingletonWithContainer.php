<?php declare(strict_types=1);

namespace Salient\Tests\Core\Utility\Get;

use Salient\Container\Contract\HasContextualBindings;
use Salient\Container\Contract\HasServices;
use Salient\Container\Contract\SingletonInterface;
use Salient\Container\ContainerInterface;

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
