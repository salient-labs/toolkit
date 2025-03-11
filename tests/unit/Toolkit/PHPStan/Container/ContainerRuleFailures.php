<?php declare(strict_types=1);

namespace Salient\Tests\PHPStan\Container;

use Salient\Contract\Container\ContainerInterface;

class A {}
class B extends A {}
class C {}

class Foo
{
    public function __construct(ContainerInterface $container)
    {
        $container->getAs(A::class, A::class);
        $container->getAs(B::class, A::class);
        $container->getAs(C::class, A::class);
        $container->getAs(A::class, B::class);
    }
}
