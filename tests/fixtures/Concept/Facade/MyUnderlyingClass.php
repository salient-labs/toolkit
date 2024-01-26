<?php declare(strict_types=1);

namespace Lkrms\Tests\Concept\Facade;

use Lkrms\Concern\UnloadsFacades;
use Lkrms\Contract\FacadeAwareInterface;

class MyUnderlyingClass implements FacadeAwareInterface
{
    use UnloadsFacades;
}
