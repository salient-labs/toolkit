<?php declare(strict_types=1);

use function Lkrms\Tests\Utility\Debug\getCaller;

/**
 * @return array<int|string>
 */
function Lkrms_Tests_Runtime_getCallerViaFunction(int $depth = 0)
{
    return getCaller($depth);
}

/**
 * @return Closure(int=): array<int|string>
 */
function Lkrms_Tests_Runtime_getFunctionCallback(): Closure
{
    return function (int $depth = 0) {
        return Lkrms_Tests_Runtime_getCallerViaFunction($depth);
    };
}
