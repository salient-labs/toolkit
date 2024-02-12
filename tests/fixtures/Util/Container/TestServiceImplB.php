<?php declare(strict_types=1);

namespace Lkrms\Tests\Container;

use Lkrms\Container\Contract\SingletonInterface;

class TestServiceImplB extends TestServiceImplA implements SingletonInterface {}
