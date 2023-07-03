<?php declare(strict_types=1);

namespace Lkrms\Tests\Concept;

use Lkrms\Tests\Concept\Facade\MyFacade;
use Lkrms\Tests\Concept\Facade\MyUnderlyingClass;

final class FacadeTest extends \Lkrms\Tests\TestCase
{
    public function testGetFuncNumArgs()
    {
        $this->assertSame(
            MyFacade::checkFuncNumArgs($count),
            MyUnderlyingClass::class . '::checkFuncNumArgs called with 1 argument(s)'
        );
        $this->assertSame($count, 1);

        $this->assertSame(
            MyFacade::checkFuncNumArgs($count2, 'Arguments: %d', $count2),
            'Arguments: 3'
        );
        $this->assertSame($count2, 3);
    }
}
