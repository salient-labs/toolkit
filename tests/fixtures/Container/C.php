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
class C implements ReceivesContainer, ReceivesService, HasContainer, HasService
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
