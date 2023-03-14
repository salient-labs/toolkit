<?php declare(strict_types=1);

namespace Lkrms\Tests\Concept\HasMutator;

use Lkrms\Concern\HasMutator;

class MyMutatingClass
{
    use HasMutator;

    public int $A;

    public $B;

    public $C = 3;

    public $Arr1 = [
        'a' => 'foo',
        'b' => 'bar',
    ];

    public $Arr2 = [];

    public array $Arr3;

    public $Arr4;

    public $Obj;

    public function __construct()
    {
        $this->Obj = new \stdClass();
    }

    public function with(string $property, $value, ?string $key = null)
    {
        return $this->withPropertyValue($property, $value, $key);
    }
}
