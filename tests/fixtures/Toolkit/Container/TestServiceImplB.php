<?php declare(strict_types=1);

namespace Salient\Tests\Container;

use Salient\Container\Contract\SingletonInterface;

class TestServiceImplB extends TestServiceImplA implements SingletonInterface {}
