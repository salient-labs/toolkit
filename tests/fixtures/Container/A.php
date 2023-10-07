<?php declare(strict_types=1);

namespace Lkrms\Tests\Container;

use Lkrms\Contract\IContainer;
use Lkrms\Contract\ReceivesContainer;
use Lkrms\Contract\ReceivesService;
use Lkrms\Contract\ReturnsContainer;
use Lkrms\Contract\ReturnsService;

/**
 * @template T of IContainer
 * @implements ReturnsContainer<T>
 */
class A implements ReceivesContainer, ReceivesService, ReturnsContainer, ReturnsService
{
    use TestTrait;

    public ITestService1 $TestService;

    public function __construct(ITestService1 $testService)
    {
        $this->TestService = $testService;
    }
}
