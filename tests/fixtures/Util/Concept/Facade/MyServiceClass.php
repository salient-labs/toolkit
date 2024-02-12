<?php declare(strict_types=1);

namespace Lkrms\Tests\Concept\Facade;

use Lkrms\Contract\Unloadable;

class MyServiceClass implements Unloadable
{
    use MyInstanceTrait;

    public function getClass(): string
    {
        return __CLASS__;
    }
}
