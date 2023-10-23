<?php declare(strict_types=1);

namespace Lkrms\Tests\Utility\Debugging;

use Lkrms\Facade\Debug;
use Closure;

class GetCallerClass
{
    /**
     * @return array<int|string>
     */
    private static function getCaller(int $depth)
    {
        return Debug::getCaller($depth);
    }

    /**
     * @return array<int|string>
     */
    public function getCallerViaMethod(int $depth = 0)
    {
        return $this->getCaller($depth);
    }

    /**
     * @return Closure(int=): array<int|string>
     */
    public function getCallback(): Closure
    {
        return function (int $depth = 0) { return $this->getCallerViaMethod($depth); };
    }

    /**
     * @return array<int|string>
     */
    public static function getCallerViaStaticMethod(int $depth = 0)
    {
        return self::getCaller($depth);
    }

    /**
     * @return Closure(int=): array<int|string>
     */
    public static function getStaticCallback(): Closure
    {
        return static function (int $depth = 0) { return self::getCallerViaStaticMethod($depth); };
    }
}
