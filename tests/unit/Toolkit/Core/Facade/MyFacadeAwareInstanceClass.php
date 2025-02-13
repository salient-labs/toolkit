<?php declare(strict_types=1);

namespace Salient\Tests\Core\Facade;

use Salient\Contract\Core\Facade\FacadeAwareInterface;
use Salient\Contract\Core\Unloadable;
use Salient\Core\Concern\FacadeAwareInstanceTrait;

/**
 * @implements FacadeAwareInterface<MyServiceInterface>
 */
class MyFacadeAwareInstanceClass implements
    FacadeAwareInterface,
    MyServiceInterface,
    Unloadable
{
    /** @use FacadeAwareInstanceTrait<MyServiceInterface> */
    use FacadeAwareInstanceTrait;
    use MyInstanceTrait;

    public function getInstanceWithoutFacade(): ?self
    {
        return $this->InstanceWithoutFacade;
    }

    public function getInstanceWithFacade(): ?self
    {
        return $this->InstanceWithFacade;
    }
}
