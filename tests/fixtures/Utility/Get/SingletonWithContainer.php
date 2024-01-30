<?php declare(strict_types=1);

namespace Lkrms\Tests\Utility\Get;

use Lkrms\Container\Contract\ContainerInterface;
use Lkrms\Contract\IServiceSingleton;

class SingletonWithContainer implements IServiceSingleton
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
