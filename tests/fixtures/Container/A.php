<?php declare(strict_types=1);

namespace Lkrms\Tests\Container;

use Lkrms\Container\Contract\ContainerAwareInterface;
use Lkrms\Container\Contract\ContainerInterface;
use Lkrms\Container\Contract\ServiceAwareInterface;
use Lkrms\Contract\HasContainer;
use Lkrms\Contract\HasService;

/**
 * @template T of ContainerInterface
 * @implements HasContainer<T>
 */
class A implements ContainerAwareInterface, ServiceAwareInterface, HasContainer, HasService
{
    use TestTrait;

    public ITestService1 $TestService;

    public function __construct(ITestService1 $testService)
    {
        $this->TestService = $testService;
    }
}
