<?php declare(strict_types=1);

namespace Lkrms\Tests\Container;

class Office
{
    public int $Id;

    public ?string $Name;

    public function __construct(IdGenerator $idGenerator, ?string $name = null)
    {
        $this->Id = $idGenerator->getNext(__CLASS__);
        $this->Name = $name;
    }
}
