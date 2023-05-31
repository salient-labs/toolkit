<?php declare(strict_types=1);

namespace Lkrms\Tests\Container;

use Lkrms\Container\Container;
use Lkrms\Container\ServiceLifetime;
use Lkrms\Contract\IContainer;
use Lkrms\Contract\IService;
use Lkrms\Contract\IServiceShared;
use Lkrms\Contract\IServiceSingleton;
use Lkrms\Contract\ReceivesContainer;
use Lkrms\Contract\ReceivesService;
use Lkrms\Contract\ReturnsContainer;
use Lkrms\Contract\ReturnsService;
use Lkrms\Exception\ContainerServiceNotFoundException;
use RuntimeException;

final class ContainerTest extends \Lkrms\Tests\TestCase
{
    public function testServiceTransient()
    {
        $container = (new Container())->service(TestServiceImplA::class, null, null, ServiceLifetime::TRANSIENT);
        $this->_testServiceTransient($container);
    }

    public function testServiceShared()
    {
        $container = (new Container())->service(TestServiceImplA::class, null, null, ServiceLifetime::SERVICE_SINGLETON);
        $this->_testServiceShared($container);
    }

    public function testServiceSingleton()
    {
        $container = (new Container())->service(TestServiceImplA::class, null, null, ServiceLifetime::SINGLETON);
        $this->_testServiceSingleton($container);
    }

    public function testServiceSharedSingleton()
    {
        $container = (new Container())->service(TestServiceImplA::class, null, null, ServiceLifetime::SINGLETON | ServiceLifetime::SERVICE_SINGLETON);
        $this->_testServiceSharedSingleton($container);
    }

    public function testServiceInherit()
    {
        $container = (new Container())->service(TestServiceImplA::class);
        $this->_testServiceTransient($container);

        $container = (new Container())->service(TestServiceImplB::class);
        $this->_testServiceSingleton($container, TestServiceImplB::class);

        $container = (new Container())->service(TestServiceImplC::class);
        $this->_testServiceShared($container, TestServiceImplC::class);

        $container = (new Container())->service(TestServiceImplD::class);
        $this->_testServiceSharedSingleton($container, TestServiceImplD::class);
    }

    public function testServiceBindings()
    {
        $container = (new Container())->service(TestServiceImplB::class);
        $ts1 = $container->get(ITestService1::class);
        $o1 = $container->get(A::class);

        $container2 = $container->inContextOf(get_class($ts1));
        $container3 = $container2->inContextOf(get_class($ts1));
        $o2 = $container2->get(A::class);

        $this->assertNotSame($container, $container2);
        $this->assertSame($container2, $container3);

        $this->assertInstanceOf(A::class, $o1);
        $this->assertNotInstanceOf(B::class, $o1);
        $this->assertInstanceOf(B::class, $o2);

        $this->assertSame($ts1, $o1->TestService);
        $this->assertSame($o1->TestService, $o2->TestService);
        $this->assertSame($container, $o1->container());
        $this->assertSame($container2, $o2->container());
        $this->assertSame(A::class, $o1->service());
        $this->assertSame(A::class, $o2->service());

        // `TestServiceImplB` is only bound to itself, so the container can't
        // inject `ITestService1` into `A::construct()` unless it's passed as a
        // parameter
        $container = (new Container())->inContextOf(TestServiceImplB::class);
        $ts2 = $container->get(TestServiceImplB::class);
        $o3 = $container->get(A::class, [$ts2]);
        $this->assertInstanceOf(B::class, $o3);

        // Without `$ts2`, the container throws an exception
        $this->expectException(ContainerServiceNotFoundException::class);
        $container->get(A::class);
    }

    private function _testServiceTransient($container, $concrete = TestServiceImplA::class)
    {
        $c1 = $container->get($concrete);
        $c2 = $container->get($concrete);
        $ts1a = $container->get(ITestService1::class);
        $ts1b = $container->get(ITestService1::class);
        $ts2a = $container->get(ITestService2::class);
        $ts2b = $container->get(ITestService2::class);
        $this->assertNotSame($ts1a, $ts1b);
        $this->assertNotSame($ts2a, $ts2b);
        $this->assertNotSame($c1, $c2);
        $this->assertNotSame($c1, $ts1a);
        $this->assertNotSame($c2, $ts1a);
        $this->assertNotSame($c1, $ts2a);
        $this->assertNotSame($c2, $ts2a);
    }

    private function _testServiceShared($container, $concrete = TestServiceImplA::class)
    {
        $c1 = $container->get($concrete);
        $c2 = $container->get($concrete);
        $ts1a = $container->get(ITestService1::class);
        $ts1b = $container->get(ITestService1::class);
        $ts2a = $container->get(ITestService2::class);
        $ts2b = $container->get(ITestService2::class);
        $this->assertSame($ts1a, $ts1b);
        $this->assertSame($ts2a, $ts2b);
        $this->assertNotSame($ts1a, $ts2a);
        $this->assertNotSame($c1, $c2);
        $this->assertNotSame($c1, $ts1a);
        $this->assertNotSame($c2, $ts1a);
        $this->assertNotSame($c1, $ts2a);
        $this->assertNotSame($c2, $ts2a);
    }

    private function _testServiceSingleton($container, $concrete = TestServiceImplA::class)
    {
        $c1 = $container->get($concrete);
        $c2 = $container->get($concrete);
        $ts1a = $container->get(ITestService1::class);
        $ts1b = $container->get(ITestService1::class);
        $ts2a = $container->get(ITestService2::class);
        $ts2b = $container->get(ITestService2::class);
        $this->assertSame($ts1a, $ts1b);
        $this->assertSame($ts2a, $ts2b);
        $this->assertSame($c1, $c2);
        $this->assertSame($c1, $ts1a);
        $this->assertSame($c1, $ts2a);
    }

    private function _testServiceSharedSingleton($container, $concrete = TestServiceImplA::class)
    {
        $c1 = $container->get($concrete);
        $c2 = $container->get($concrete);
        $ts1a = $container->get(ITestService1::class);
        $ts1b = $container->get(ITestService1::class);
        $ts2a = $container->get(ITestService2::class);
        $ts2b = $container->get(ITestService2::class);
        $this->assertSame($ts1a, $ts1b);
        $this->assertSame($ts2a, $ts2b);
        $this->assertNotSame($ts1a, $ts2a);
        $this->assertSame($c1, $c2);
        $this->assertNotSame($c1, $ts1a);
        $this->assertNotSame($c1, $ts2a);
    }
}

interface ITestService1
{
}

interface ITestService2
{
}

class TestServiceImplA implements IService, ITestService1, ITestService2
{
    public static function getServices(): array
    {
        return [ITestService1::class, ITestService2::class];
    }

    public static function getContextualBindings(): array
    {
        return [A::class => B::class];
    }
}

class TestServiceImplB extends TestServiceImplA implements IServiceSingleton
{
}

class TestServiceImplC extends TestServiceImplA implements IServiceShared
{
}

class TestServiceImplD extends TestServiceImplB implements IServiceShared
{
}

/**
 * @template T of IContainer
 * @implements ReturnsContainer<T>
 */
class A implements ReceivesContainer, ReceivesService, ReturnsContainer, ReturnsService
{
    public ITestService1 $TestService;

    protected ?IContainer $Container = null;

    protected ?string $Service = null;

    public function __construct(ITestService1 $testService)
    {
        $this->TestService = $testService;
    }

    public function service()
    {
        return $this->Service;
    }

    public function app(): IContainer
    {
        return $this->container();
    }

    public function container(): IContainer
    {
        return $this->Container ?: ($this->Container = new Container());
    }

    public function setContainer(IContainer $container)
    {
        if ($this->Container) {
            throw new RuntimeException('setContainer already called');
        }
        $this->Container = $container;

        return $this;
    }

    public function setService(string $id)
    {
        if ($this->Service) {
            throw new RuntimeException('setService already called');
        }
        $this->Service = $id;

        return $this;
    }
}

/**
 * @template T of IContainer
 * @extends A<T>
 */
class B extends A
{
}
