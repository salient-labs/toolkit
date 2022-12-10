<?php declare(strict_types=1);

namespace Lkrms\Tests\Utility\Debugging;

use Lkrms\Facade\Debug;

class GetCallerClass
{
    private static function getCaller($depth)
    {
        return Debug::getCaller($depth);
    }

    public function getCallerViaMethod($depth = 0)
    {
        return $this->getCaller($depth);
    }

    public function getCallback()
    {
        return function ($depth = 0) {return $this->getCallerViaMethod($depth);};
    }

    public static function getCallerViaStaticMethod($depth = 0)
    {
        return self::getCaller($depth);
    }

    public static function getStaticCallback()
    {
        return static function ($depth = 0) {return self::getCallerViaStaticMethod($depth);};
    }
}
