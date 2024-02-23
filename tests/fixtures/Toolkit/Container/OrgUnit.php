<?php declare(strict_types=1);

namespace Salient\Tests\Container;

use Salient\Container\Contract\HasContextualBindings;
use Salient\Container\Contract\HasServices;

class OrgUnit implements HasServices, HasContextualBindings
{
    public Office $MainOffice;

    public Department $Department;

    public Staff $Manager;

    public User $Admin;

    public function __construct(Office $mainOffice, Department $department, Staff $manager, User $admin)
    {
        $this->MainOffice = $mainOffice;
        $this->Department = $department;
        $this->Manager = $manager;
        $this->Admin = $admin;
    }

    public static function getServices(): array
    {
        return [];
    }

    public static function getContextualBindings(): array
    {
        return [
            Office::class => FancyOffice::class,
            User::class => DepartmentStaff::class,
            Staff::class => DepartmentStaff::class,
        ];
    }
}
