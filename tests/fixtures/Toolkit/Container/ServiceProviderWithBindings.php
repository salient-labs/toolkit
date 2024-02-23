<?php declare(strict_types=1);

namespace Salient\Tests\Container;

use Salient\Container\Contract\HasBindings;

class ServiceProviderWithBindings implements HasBindings
{
    public static function getBindings(): array
    {
        return [
            User::class => DepartmentStaff::class,
            Staff::class => DepartmentStaff::class,
        ];
    }

    public static function getSingletons(): array
    {
        return [
            IdGenerator::class,
        ];
    }
}
