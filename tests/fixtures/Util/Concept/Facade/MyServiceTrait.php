<?php declare(strict_types=1);

namespace Lkrms\Tests\Concept\Facade;

trait MyServiceTrait
{
    use MyInstanceTrait;

    public function getMethod(): string
    {
        return __METHOD__;
    }
}
