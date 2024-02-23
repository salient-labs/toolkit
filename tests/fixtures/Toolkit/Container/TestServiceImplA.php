<?php declare(strict_types=1);

namespace Salient\Tests\Container;

use Salient\Container\Contract\HasContextualBindings;
use Salient\Container\Contract\HasServices;

class TestServiceImplA implements HasServices, HasContextualBindings, ITestService1, ITestService2
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
