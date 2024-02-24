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
class C implements ContainerAwareInterface, ServiceAwareInterface, HasContainer
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
