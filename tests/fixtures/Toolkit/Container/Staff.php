<?php declare(strict_types=1);

namespace Salient\Tests\Container;

class Staff extends User
{
    public int $StaffId;

    public function __construct(IdGenerator $idGenerator, Office $office)
    {
        parent::__construct($idGenerator, $office);
        $this->StaffId = $idGenerator->getNext(__CLASS__);
    }
}
