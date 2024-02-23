<?php declare(strict_types=1);

namespace Salient\Tests\Container;

class User
{
    public int $Id;

    public Office $Office;

    public function __construct(IdGenerator $idGenerator, Office $office)
    {
        $this->Id = $idGenerator->getNext(__CLASS__);
        $this->Office = $office;
    }
}
