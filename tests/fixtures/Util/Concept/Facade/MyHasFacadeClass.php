<?php declare(strict_types=1);

namespace Lkrms\Tests\Concept\Facade;

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
    use MyServiceTrait;
}
