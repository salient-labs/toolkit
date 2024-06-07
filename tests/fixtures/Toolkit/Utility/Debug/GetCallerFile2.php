<?php declare(strict_types=1);

use function Salient\Tests\Core\Utility\Debug\getCaller;

/**
 * @return array<int|string>
 */
function Salient_Tests_Core_Utility_Debug_getCallerViaFunction(int $depth = 0)
{
    return getCaller($depth);
}

/**
 * @return Closure(int=): array<int|string>
 */
function Salient_Tests_Core_Utility_Debug_getFunctionCallback(): Closure
{
    return function (int $depth = 0) {
        return Salient_Tests_Core_Utility_Debug_getCallerViaFunction($depth);
    };
}
