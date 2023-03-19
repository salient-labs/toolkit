<?php declare(strict_types=1);

namespace Lkrms\Tests\Concept\TypedCollection;

use Lkrms\Concept\TypedCollection;

/**
 * @extends TypedCollection<MyClass>
 */
class MyCollection extends TypedCollection
{
    protected function compareItems($a, $b, bool $strict = false): int
    {
        return strlen($b->Name) - strlen($a->Name);
    }

    protected function getItemClass(): string
    {
        return MyClass::class;
    }
}
