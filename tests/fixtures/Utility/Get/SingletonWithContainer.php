<?php declare(strict_types=1);

namespace Lkrms\Tests\Utility\Get;

use Lkrms\Contract\IContainer;
use Lkrms\Contract\IServiceSingleton;

class SingletonWithContainer implements IServiceSingleton
{
    public IContainer $Container;

    public function __construct(IContainer $container)
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
