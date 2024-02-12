<?php declare(strict_types=1);

namespace Lkrms\Tests\Concept\Facade;

use Lkrms\Concern\UnloadsFacades;
use Lkrms\Contract\FacadeAwareInterface;
use Lkrms\Contract\FacadeInterface;
use Lkrms\Contract\Unloadable;

/**
 * @implements FacadeAwareInterface<FacadeInterface<self>>
 */
class MyUnloadsFacadesClass extends MyServiceClass implements
    FacadeAwareInterface,
    MyServiceInterface,
    Unloadable
{
    /** @use UnloadsFacades<FacadeInterface<self>> */
    use UnloadsFacades;
    use MyServiceTrait;
}
