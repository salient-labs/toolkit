<?php declare(strict_types=1);

namespace Lkrms\Tests\Concept\Facade;

use Salient\Core\Concern\UnloadsFacades;
use Salient\Core\Contract\FacadeAwareInterface;
use Salient\Core\Contract\FacadeInterface;
use Salient\Core\Contract\Unloadable;

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
