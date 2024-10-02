<?php declare(strict_types=1);

namespace Salient\Tests\Core\AbstractFacade;

use Salient\Contract\Core\Instantiable;
use Salient\Contract\Core\Unloadable;

class MyServiceClass implements Instantiable, Unloadable
{
    use MyInstanceTrait;
}
