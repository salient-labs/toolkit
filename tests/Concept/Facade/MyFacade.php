<?php declare(strict_types=1);

namespace Lkrms\Tests\Concept\Facade;

use Lkrms\Concept\Facade;

final class MyFacade extends Facade
{
    protected static function getServiceName(): string
    {
        return MyUnderlyingClass::class;
    }

    public static function checkFuncNumArgs(int &$numArgs = null, string $format = '', &...$values): string
    {
        static::setFuncNumArgs(__FUNCTION__, func_num_args());
        try {
            return static::getInstance()->checkFuncNumArgs($numArgs, $format, ...$values);
        } finally {
            static::clearFuncNumArgs(__FUNCTION__);
        }
    }
}
