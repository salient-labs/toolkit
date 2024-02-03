<?php declare(strict_types=1);

namespace Lkrms\Tests\Container;

use Lkrms\Container\Contract\ServiceSingletonInterface;

class TestServiceImplC extends TestServiceImplA implements ServiceSingletonInterface {}
