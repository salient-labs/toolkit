<?php declare(strict_types=1);

namespace Lkrms\Tests\Container;

class DepartmentStaff extends Staff
{
    public Department $Department;

    public function __construct(IdGenerator $idGenerator, Office $office, Department $department)
    {
        parent::__construct($idGenerator, $office);
        $this->Department = $department;
    }
}
