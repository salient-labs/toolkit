<?php declare(strict_types=1);

namespace Lkrms\Tests\Concern\HasMutator;

use Lkrms\Concept\TypedCollection;

/**
 * @extends TypedCollection<array-key,\stdClass>
 */
class MyArrayAccessClass extends TypedCollection
{
    protected const ITEM_CLASS = \stdClass::class;
}
