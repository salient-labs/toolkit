<?php declare(strict_types=1);

namespace Salient\Tests\Core\Concern\ImmutableTrait;

use Salient\Collection\ArrayableCollectionTrait;
use Salient\Collection\CollectionTrait;
use Salient\Collection\ReadOnlyArrayAccessTrait;
use Salient\Contract\Collection\CollectionInterface;
use Salient\Contract\Core\Immutable;
use Salient\Core\Concern\ImmutableTrait;
use IteratorAggregate;
use stdClass;

class MyImmutableClass implements Immutable
{
    use ImmutableTrait {
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
    /** @var MyCollection */
    public $Coll;
    public stdClass $TypedObj;

    public function __construct()
    {
        $this->Obj = new stdClass();
        $this->Coll = new MyCollection();
    }
}

/**
 * @implements CollectionInterface<array-key,stdClass>
 * @implements IteratorAggregate<array-key,stdClass>
 */
class MyCollection implements CollectionInterface, IteratorAggregate
{
    /** @use CollectionTrait<array-key,stdClass,static> */
    use CollectionTrait;
    /** @use ReadOnlyArrayAccessTrait<array-key,stdClass> */
    use ReadOnlyArrayAccessTrait {
        ReadOnlyArrayAccessTrait::offsetSet insteadof CollectionTrait;
        ReadOnlyArrayAccessTrait::offsetUnset insteadof CollectionTrait;
    }
    /** @use ArrayableCollectionTrait<array-key,stdClass> */
    use ArrayableCollectionTrait;
}
