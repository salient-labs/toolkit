<?php declare(strict_types=1);

namespace Lkrms\Tests\Container;

use Lkrms\Container\Contract\ServiceSingletonInterface;

class TestServiceImplD extends TestServiceImplB implements ServiceSingletonInterface {}
