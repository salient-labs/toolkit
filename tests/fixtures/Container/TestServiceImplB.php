<?php declare(strict_types=1);

namespace Lkrms\Tests\Container;

use Lkrms\Contract\IServiceSingleton;

class TestServiceImplB extends TestServiceImplA implements IServiceSingleton {}
