<?php declare(strict_types=1);

namespace Salient\Tests\Core\AbstractFacade;

use Salient\Core\Contract\Unloadable;

// The declaration is only to satisfy static analysis
//
// @phpstan-ignore-next-line
if (false) {
    class MyNonExistentClass implements MyServiceInterface, Unloadable
    {
        use MyInstanceTrait;
    }
}
