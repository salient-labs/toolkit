<?php declare(strict_types=1);

namespace Lkrms\Tests\Concept\TypedCollection;

use Lkrms\Concept\TypedCollection;

/**
 * @extends TypedCollection<array-key,MyClass>
 */
class MyCollection extends TypedCollection
{
    protected const ITEM_CLASS = MyClass::class;

    protected function compareItems($a, $b): int
    {
        return strlen($b->Name) - strlen($a->Name);
    }
}
