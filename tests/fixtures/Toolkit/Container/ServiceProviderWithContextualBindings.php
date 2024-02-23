<?php declare(strict_types=1);

namespace Salient\Tests\Container;

use Salient\Container\Contract\HasContextualBindings;

class ServiceProviderWithContextualBindings implements HasContextualBindings
{
    public static function getContextualBindings(): array
    {
        return [
            Office::class => FancyOffice::class,
            User::class => DepartmentStaff::class,
            Staff::class => DepartmentStaff::class,
        ];
    }
}
