<?php declare(strict_types=1);

namespace Lkrms\Tests\Concept\TypedList;

use Lkrms\Concept\TypedList;

/**
 * @extends TypedList<MyClass>
 */
class MyList extends TypedList
{
    protected function compareItems($a, $b): int
    {
        return strlen($b->Name) - strlen($a->Name);
    }
}
