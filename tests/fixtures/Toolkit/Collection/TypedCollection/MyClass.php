<?php declare(strict_types=1);

namespace Salient\Tests\Collection\TypedCollection;

class MyClass
{
    /** @var string */
    public $Name;

    public function __construct(string $name)
    {
        $this->Name = $name;
    }
}
