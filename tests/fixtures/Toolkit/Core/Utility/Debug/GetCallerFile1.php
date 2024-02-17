<?php declare(strict_types=1);

namespace Salient\Tests\Core\Utility\Debug;

use Salient\Core\Utility\Debug;
use Closure;

/**
 * @return array<int|string>
 */
function getCaller(int $depth)
{
    return Debug::getCaller($depth);
}

/**
 * @return array<int|string>
 */
function getCallerViaFunction(int $depth = 0)
{
    return getCaller($depth);
}

/**
 * @return Closure(int=): array<int|string>
 */
function getFunctionCallback(): Closure
{
    return function (int $depth = 0) { return getCallerViaFunction($depth); };
}
