<?php declare(strict_types=1);

namespace Salient\Tests\Collection\TypedList;

use Salient\Collection\ListCollection;

/**
 * @extends ListCollection<MyClass>
 */
class MyList extends ListCollection
{
    protected function compareItems($a, $b): int
    {
        return strlen($b->Name) - strlen($a->Name);
    }
}
