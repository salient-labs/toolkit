<?php declare(strict_types=1);

namespace Lkrms\Tests\Container;

use Lkrms\Container\Contract\ContainerInterface;
use Lkrms\Contract\HasContainer;
use Lkrms\Contract\HasService;
use Lkrms\Contract\ReceivesContainer;
use Lkrms\Contract\ReceivesService;

/**
 * @template T of ContainerInterface
 * @implements HasContainer<T>
 */
class A implements ReceivesContainer, ReceivesService, HasContainer, HasService
{
    use TestTrait;

    public ITestService1 $TestService;

    public function __construct(ITestService1 $testService)
    {
        $this->TestService = $testService;
    }
}
