<?php declare(strict_types=1);

namespace Lkrms\Facade;

use Lkrms\Concept\Facade;
use Lkrms\Support\Catalog\ArrayKeyConformity;
use Lkrms\Support\Catalog\ArrayMapperFlag;
use Lkrms\Support\ArrayMapper;
use Closure;

/**
 * A facade for \Lkrms\Support\ArrayMapper
 *
 * @method static ArrayMapper load() Load and return an instance of the underlying ArrayMapper class
 * @method static ArrayMapper getInstance() Get the underlying ArrayMapper instance
 * @method static bool isLoaded() True if an underlying ArrayMapper instance has been loaded
 * @method static void unload() Clear the underlying ArrayMapper instance
 * @method static Closure(array<array-key,mixed>): array<array-key,mixed> getKeyMapClosure(array<array-key,array-key|array-key[]> $keyMap, ArrayKeyConformity::* $conformity = ArrayKeyConformity::NONE, int-mask-of<ArrayMapperFlag::*> $flags = ArrayMapperFlag::ADD_UNMAPPED) Get a closure to move array values from one set of keys to another (see {@see ArrayMapper::getKeyMapClosure()})
 *
 * @uses ArrayMapper
 *
 * @extends Facade<ArrayMapper>
 */
final class Mapper extends Facade
{
    /**
     * @inheritDoc
     */
    protected static function getServiceName(): string
    {
        return ArrayMapper::class;
    }
}
