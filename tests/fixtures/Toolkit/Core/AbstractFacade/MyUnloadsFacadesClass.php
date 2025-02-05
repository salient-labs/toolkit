<?php declare(strict_types=1);

namespace Salient\Tests\Core\AbstractFacade;

use Salient\Contract\Core\Facade\FacadeAwareInterface;
use Salient\Contract\Core\Unloadable;
use Salient\Core\Concern\UnloadsFacades;

/**
 * @implements FacadeAwareInterface<MyServiceInterface>
 */
class MyUnloadsFacadesClass extends MyServiceClass implements
    FacadeAwareInterface,
    MyServiceInterface,
    Unloadable
{
    /** @use UnloadsFacades<MyServiceInterface> */
    use UnloadsFacades;
    use MyInstanceTrait;
}
