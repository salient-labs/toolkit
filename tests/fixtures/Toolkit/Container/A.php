<?php declare(strict_types=1);

namespace Salient\Tests\Container;

use Salient\Container\Contract\ContainerAwareInterface;
use Salient\Container\Contract\HasContainer;
use Salient\Container\Contract\ServiceAwareInterface;
use Salient\Container\ContainerInterface;

/**
 * @template T of ContainerInterface
 *
 * @implements HasContainer<T>
 */
class A implements ContainerAwareInterface, ServiceAwareInterface, HasContainer
{
    use TestTrait;

    public ITestService1 $TestService;

    public function __construct(ITestService1 $testService)
    {
        $this->TestService = $testService;
    }
}
