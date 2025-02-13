<?php declare(strict_types=1);

namespace Salient\Tests\Core\Facade;

use Salient\Contract\Core\Unloadable;

// The declaration is only to satisfy static analysis
//
// @phpstan-ignore if.alwaysFalse
if (false) {
    class MyNonExistentClass implements MyServiceInterface, Unloadable
    {
        use MyInstanceTrait;
    }
}
