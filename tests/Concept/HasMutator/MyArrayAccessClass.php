<?php declare(strict_types=1);

namespace Lkrms\Tests\Concept\HasMutator;

use Lkrms\Concept\TypedCollection;

class MyArrayAccessClass extends TypedCollection
{
    protected function getItemClass(): string
    {
        return \stdClass::class;
    }
}
