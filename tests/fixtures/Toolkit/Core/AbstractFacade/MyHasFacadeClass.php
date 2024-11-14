<?php declare(strict_types=1);

namespace Salient\Tests\Core\AbstractFacade;

use Salient\Contract\Core\FacadeAwareInterface;
use Salient\Contract\Core\FacadeInterface;
use Salient\Contract\Core\Unloadable;
use Salient\Core\Concern\HasFacade;

/**
 * @implements FacadeAwareInterface<FacadeInterface<MyServiceInterface>>
 */
class MyHasFacadeClass implements
    FacadeAwareInterface,
    MyServiceInterface,
    Unloadable
{
    /** @use HasFacade<FacadeInterface<MyServiceInterface>> */
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
