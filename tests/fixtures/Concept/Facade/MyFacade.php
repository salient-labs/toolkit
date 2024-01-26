<?php declare(strict_types=1);

namespace Lkrms\Tests\Concept\Facade;

use Lkrms\Concept\Facade;

/**
 * @extends Facade<MyUnderlyingClass>
 */
final class MyFacade extends Facade
{
    protected static function getService(): string
    {
        return MyUnderlyingClass::class;
    }
}
