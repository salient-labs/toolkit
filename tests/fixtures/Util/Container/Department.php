<?php declare(strict_types=1);

namespace Lkrms\Tests\Container;

class Department
{
    public int $Id;

    public ?string $Name;

    public Office $MainOffice;

    public function __construct(IdGenerator $idGenerator, Office $mainOffice, ?string $name = null)
    {
        $this->Id = $idGenerator->getNext(__CLASS__);
        $this->Name = $name;
        $this->MainOffice = $mainOffice;
    }
}
