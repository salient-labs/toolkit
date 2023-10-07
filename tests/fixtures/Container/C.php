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
class C implements ReceivesContainer, ReceivesService, ReturnsContainer, ReturnsService
{
    use TestTrait;

    /**
     * @var A<T>
     */
    public A $a;

    /**
     * @param A<T> $a
     */
    public function __construct(A $a)
    {
        $this->a = $a;
    }
}
