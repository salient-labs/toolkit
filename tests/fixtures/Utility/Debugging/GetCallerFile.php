<?php declare(strict_types=1);

namespace Lkrms\Tests\Utility\Debugging
{
    use Lkrms\Facade\Debug;

    function getCaller($depth)
    {
        return Debug::getCaller($depth);
    }

    function getCallerViaFunction($depth = 0)
    {
        return getCaller($depth);
    }

    function getFunctionCallback()
    {
        return function ($depth = 0) { return getCallerViaFunction($depth); };
    }
}

namespace
{
    use function Lkrms\Tests\Utility\Debugging\getCaller;

    function Lkrms_Tests_Runtime_getCallerViaFunction($depth = 0)
    {
        return getCaller($depth);
    }

    function Lkrms_Tests_Runtime_getFunctionCallback()
    {
        return function ($depth = 0) {
            return Lkrms_Tests_Runtime_getCallerViaFunction($depth);
        };
    }
}
