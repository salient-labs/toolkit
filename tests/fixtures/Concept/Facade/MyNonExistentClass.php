<?php declare(strict_types=1);

namespace Lkrms\Tests\Concept\Facade;

use Lkrms\Contract\Unloadable;

// The declaration is only to satisfy static analysis
//
// @phpstan-ignore-next-line
if (false) {
    class MyNonExistentClass implements MyServiceInterface, Unloadable
    {
        use MyServiceTrait;
    }
}
