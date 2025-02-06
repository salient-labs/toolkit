<?php declare(strict_types=1);

namespace Salient\Tests\Core\Facade;

use Salient\Contract\Core\Facade\FacadeAwareInterface;
use Salient\Contract\Core\Unloadable;
use Salient\Core\Concern\FacadeAwareTrait;

/**
 * @implements FacadeAwareInterface<MyServiceInterface>
 */
class MyFacadeAwareClass extends MyServiceClass implements
    FacadeAwareInterface,
    MyServiceInterface,
    Unloadable
{
    /** @use FacadeAwareTrait<MyServiceInterface> */
    use FacadeAwareTrait;
    use MyInstanceTrait;
}
