<?php declare(strict_types=1);

namespace Salient\Tests\Core\AbstractFacade;

use Salient\Core\Concern\HasFacade;
use Salient\Core\Contract\FacadeAwareInterface;
use Salient\Core\Contract\FacadeInterface;
use Salient\Core\Contract\Unloadable;

/**
 * @implements FacadeAwareInterface<FacadeInterface<self>>
 */
class MyHasFacadeClass implements
    FacadeAwareInterface,
    MyServiceInterface,
    Unloadable
{
    /** @use HasFacade<FacadeInterface<self>> */
    use HasFacade;
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
