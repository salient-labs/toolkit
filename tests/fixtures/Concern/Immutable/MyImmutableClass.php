<?php declare(strict_types=1);

namespace Lkrms\Tests\Concern\Immutable;

use Lkrms\Concern\Immutable;
use Lkrms\Support\ImmutableCollection;

class MyImmutableClass
{
    use Immutable;

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
     * @var array<string,string>|null
     */
    public $Arr4;

    /**
     * @var \stdClass
     */
    public $Obj;

    /**
     * @var ImmutableCollection<array-key,mixed>
     */
    public $Coll;

    public function __construct()
    {
        $this->Obj = new \stdClass();
        $this->Coll = new ImmutableCollection();
    }

    /**
     * @param mixed $value
     * @return static
     */
    public function with(string $property, $value)
    {
        return $this->withPropertyValue($property, $value);
    }
}