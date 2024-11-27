<?php declare(strict_types=1);

namespace Salient\Tests\Core\Concern\HasMutator;

use Salient\Collection\CollectionTrait;
use Salient\Collection\ReadOnlyArrayAccessTrait;
use Salient\Contract\Collection\CollectionInterface;
use Salient\Contract\Core\Immutable;
use Salient\Core\Concern\HasMutator;
use stdClass;

class MyImmutableClass implements Immutable
{
    use HasMutator {
        with as public;
        without as public;
    }

    public int $A;
    /** @var int */
    public $B;
    /** @var int */
    public $C = 3;

    /**
     * @var array<string,string>
     */
    public $Arr1 = [
        'a' => 'foo',
        'b' => 'bar',
    ];

    /** @var array<string,string> */
    public $Arr2 = [];
    /** @var array<string,string> */
    public array $Arr3;
    /** @var array<string,string>|null */
    public $Arr4;
    /** @var stdClass */
    public $Obj;
    /** @var MyImmutableCollection */
    public $Coll;
    public stdClass $TypedObj;

    public function __construct()
    {
        $this->Obj = new stdClass();
        $this->Coll = new MyImmutableCollection();
    }
}

/**
 * @implements CollectionInterface<array-key,mixed>
 */
class MyImmutableCollection implements CollectionInterface, Immutable
{
    /** @use CollectionTrait<array-key,mixed> */
    use CollectionTrait;
    /** @use ReadOnlyArrayAccessTrait<array-key,mixed> */
    use ReadOnlyArrayAccessTrait {
        ReadOnlyArrayAccessTrait::offsetSet insteadof CollectionTrait;
        ReadOnlyArrayAccessTrait::offsetUnset insteadof CollectionTrait;
    }
}
