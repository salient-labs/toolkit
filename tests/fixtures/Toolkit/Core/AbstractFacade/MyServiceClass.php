<?php declare(strict_types=1);

namespace Salient\Tests\Core\AbstractFacade;

use Salient\Core\Contract\Unloadable;

class MyServiceClass implements Unloadable
{
    use MyInstanceTrait;
}
