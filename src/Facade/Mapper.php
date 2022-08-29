<?php

declare(strict_types=1);

namespace Lkrms\Facade;

use Closure;
use Lkrms\Concept\Facade;
use Lkrms\Support\ArrayMapper;

/**
 * A facade for ArrayMapper
 *
 * @method static Closure getKeyMapClosure(array<int|string,int|string|array<int,int|string>> $keyMap, int $conformity = \Lkrms\Support\ArrayKeyConformity::NONE, int $flags = 0) Get a closure to move array values from one set of keys to another
 *
 * @uses ArrayMapper
 * @lkrms-generate-command lk-util generate facade --class='Lkrms\Support\ArrayMapper' --generate='Lkrms\Facade\Mapper'
 */
final class Mapper extends Facade
{
    /**
     * @internal
     */
    protected static function getServiceName(): string
    {
        return ArrayMapper::class;
    }
}
