<?php declare(strict_types=1);

namespace Lkrms\Tests\Container;

use Lkrms\Contract\IService;

class TestServiceImplA implements IService, ITestService1, ITestService2
{
    public static function getServices(): array
    {
        return [ITestService1::class, ITestService2::class];
    }

    public static function getContextualBindings(): array
    {
        return [A::class => B::class];
    }
}
