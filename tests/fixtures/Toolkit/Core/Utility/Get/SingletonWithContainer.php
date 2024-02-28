<?php declare(strict_types=1);

namespace Salient\Tests\Core\Utility\Get;

use Salient\Contract\Container\ContainerInterface;
use Salient\Contract\Container\HasContextualBindings;
use Salient\Contract\Container\HasServices;
use Salient\Contract\Container\SingletonInterface;

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
