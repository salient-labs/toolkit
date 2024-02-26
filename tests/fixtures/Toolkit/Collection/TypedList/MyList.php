<?php declare(strict_types=1);

namespace Salient\Tests\Collection\TypedList;

use Salient\Collection\AbstractTypedList;

/**
 * @extends AbstractTypedList<MyClass>
 */
class MyList extends AbstractTypedList
{
    protected function compareItems($a, $b): int
    {
        return strlen($b->Name) - strlen($a->Name);
    }
}
