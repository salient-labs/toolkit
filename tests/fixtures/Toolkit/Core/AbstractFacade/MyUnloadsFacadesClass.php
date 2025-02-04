<?php declare(strict_types=1);

namespace Salient\Tests\Core\AbstractFacade;

use Salient\Contract\Core\Facade\FacadeAwareInterface;
use Salient\Contract\Core\Facade\FacadeInterface;
use Salient\Contract\Core\Unloadable;
use Salient\Core\Concern\UnloadsFacades;

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
    use MyInstanceTrait;
}
