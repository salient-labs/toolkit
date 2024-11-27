<?php declare(strict_types=1);

namespace Salient\Tests\Collection\TypedCollection;

use Salient\Collection\Collection;

/**
 * @extends Collection<array-key,MyClass>
 */
class MyCollection extends Collection
{
    protected function compareItems($a, $b): int
    {
        return strlen($b->Name) - strlen($a->Name);
    }
}
