<?php declare(strict_types=1);

namespace Lkrms\Tests\Concept\TypedList;

class MyClass
{
    /**
     * @var string
     */
    public $Name;

    public function __construct(string $name)
    {
        $this->Name = $name;
    }
}
