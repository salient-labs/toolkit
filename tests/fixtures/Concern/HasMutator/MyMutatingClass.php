<?php declare(strict_types=1);

namespace Lkrms\Tests\Concern\HasMutator;

use Lkrms\Concern\HasMutator;

class MyMutatingClass
{
    use HasMutator {
        asNew as public;
    }

    public int $A;

    /**
     * @var int
     */
    public $B;

    /**
     * @var int
     */
    public $C = 3;

    /**
     * @var array<string,string>
     */
    public $Arr1 = [
        'a' => 'foo',
        'b' => 'bar',
    ];

    /**
     * @var array<string,string>
     */
    public $Arr2 = [];

    /**
     * @var array<string,string>
     */
    public array $Arr3;

    /**
     * @var array<string,string>
     */
    public $Arr4;

    /**
     * @var \stdClass
     */
    public $Obj;

    /**
     * @var MyArrayAccessClass
     */
    public $Coll;

    public function __construct()
    {
        $this->Obj = new \stdClass();
        $this->Coll = new MyArrayAccessClass();
    }

    /**
     * @param mixed $value
     * @return $this
     */
    public function with(string $property, $value, ?string $key = null)
    {
        return $this->withPropertyValue($property, $value, $key);
    }
}
