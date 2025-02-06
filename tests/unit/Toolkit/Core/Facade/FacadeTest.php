<?php declare(strict_types=1);

namespace Salient\Tests\Core\Facade;

use Salient\Container\Container;
use Salient\Core\Facade\App;
use Salient\Core\Facade\Event;
use Salient\Core\Facade\Facade;
use Salient\Tests\TestCase;
use LogicException;
use stdClass;

/**
 * @covers \Salient\Core\Facade\Facade
 * @covers \Salient\Core\Concern\FacadeAwareTrait
 * @covers \Salient\Core\Concern\FacadeAwareInstanceTrait
 */
final class FacadeTest extends TestCase
{
    public function testBrokenFacade(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Service not instantiable: ');
        MyBrokenFacade::load();
    }

    public function testBrokenFacadeWithInstance(): void
    {
        $this->expectNotToPerformAssertions();
        MyBrokenFacade::load(new MyFacadeAwareClass());
    }

    public function testLoadAndSwap(): void
    {
        $this->assertFalse(MyInterfaceFacade::isLoaded());
        MyInterfaceFacade::load();
        $this->assertTrue(MyInterfaceFacade::isLoaded());
        $this->assertSame([], MyInterfaceFacade::getArgs());
        $this->assertSame(1, MyInterfaceFacade::getClones());
        $instance = MyInterfaceFacade::getInstance();
        $this->assertInstanceOf(MyFacadeAwareInstanceClass::class, $instance);
        $this->assertSame([], $instance->getArgs());
        $this->assertSame(0, $instance->getClones());

        $this->assertCount(0, MyFacadeAwareInstanceClass::getUnloaded());
        MyInterfaceFacade::swap($instance);
        // @phpstan-ignore method.impossibleType
        $this->assertCount(1, $unloaded = MyFacadeAwareInstanceClass::getUnloaded());
        // @phpstan-ignore method.impossibleType
        $this->assertInstanceOf(MyFacadeAwareInstanceClass::class, $unloaded = reset($unloaded));
        $this->assertNotSame($instance, $unloaded);
        $this->assertSame(2, $unloaded->getClones());
        $this->assertSame($instance, MyInterfaceFacade::getInstance());

        MyInterfaceFacade::withArgs();
        $this->assertSame(1, MyInterfaceFacade::getClones());
        $this->assertSame($instance, MyInterfaceFacade::getInstance());

        MyInterfaceFacade::withArgs(__METHOD__, $line = __LINE__);
        $this->assertSame(2, MyInterfaceFacade::getClones());
        $this->assertNotSame($instance, $instance2 = MyInterfaceFacade::getInstance());
        $this->assertSame([__METHOD__, $line], MyInterfaceFacade::getArgs());

        MyInterfaceFacade::swap($instance2);
        $this->assertSame(2, MyInterfaceFacade::getClones());
        $this->assertSame($instance2, MyInterfaceFacade::getInstance());

        $instance = new MyFacadeAwareClass(__METHOD__, $line = __LINE__);
        MyInterfaceFacade::swap($instance);
        $this->assertSame([__METHOD__, $line], MyInterfaceFacade::getArgs());
        $this->assertCount(4, MyFacadeAwareInstanceClass::getUnloaded());
    }

    public function testLoadInvalidInstance(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage(' does not inherit ');
        // @phpstan-ignore argument.type
        MyClassFacade::load(new MyFacadeAwareInstanceClass());
    }

    public function testSwapInvalidInstance(): void
    {
        MyClassFacade::load();
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage(' does not inherit ');
        // @phpstan-ignore argument.type
        MyClassFacade::swap(new MyFacadeAwareInstanceClass());
    }

    public function testAlreadyLoaded(): void
    {
        $this->assertFalse(MyClassFacade::isLoaded());
        MyClassFacade::load();
        $this->assertTrue(MyClassFacade::isLoaded());
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Already loaded: ');
        MyClassFacade::load();
    }

    public function testUnload(): void
    {
        $this->assertFalse(MyClassFacade::isLoaded());
        $instance = MyClassFacade::getInstance();
        $this->assertTrue(MyClassFacade::isLoaded());
        MyClassFacade::unload();
        $this->assertSame([$instance], MyServiceClass::getUnloaded());

        $this->assertFalse(MyInterfaceFacade::isLoaded());
        MyInterfaceFacade::withArgs(__METHOD__, __LINE__);
        $this->assertTrue(MyInterfaceFacade::isLoaded());
        $instance = MyInterfaceFacade::getInstance();
        $this->assertInstanceOf(MyFacadeAwareInstanceClass::class, $instance);
        /** @var MyFacadeAwareInstanceClass $instance */
        $this->assertNotNull($instance->getInstanceWithFacade());
        $this->assertNotNull($instance->getInstanceWithoutFacade());
        MyInterfaceFacade::unload();
        $this->assertCount(2, $unloaded = MyFacadeAwareInstanceClass::getUnloaded());
        $this->assertInstanceOf(MyFacadeAwareInstanceClass::class, $unloaded = end($unloaded));
        $this->assertNotSame($instance, $unloaded);
        $this->assertSame(3, $unloaded->getClones());
        $this->assertNull($unloaded->getInstanceWithFacade());
        $this->assertNull($unloaded->getInstanceWithoutFacade());
    }

    public function testUnloadNotLoaded(): void
    {
        $this->assertFalse(MyClassFacade::isLoaded());
        MyClassFacade::unload();
        $this->assertSame([], MyServiceClass::getUnloaded());
    }

    public function testUnloadEvent(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage(sprintf('%s cannot be unloaded before other facades', Event::class));
        MyClassFacade::load();
        Event::unload();
    }

    public function testLoadWithGlobalContainer(): void
    {
        $container = Container::getGlobalContainer();
        $this->assertInstanceOf(MyFacadeAwareInstanceClass::class, MyInterfaceFacade::getInstance());
        $this->assertInstanceOf(MyServiceClass::class, MyClassFacade::getInstance());
        $this->assertFalse($container->hasInstance(MyFacadeAwareInstanceClass::class));
        $this->assertTrue($container->hasInstance(MyServiceInterface::class));
        $this->assertTrue($container->hasInstance(MyServiceClass::class));
    }

    public function testBrokenFacadeWithContainer(): void
    {
        Container::getGlobalContainer();
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Service not instantiable: ');
        MyBrokenFacade::load();
    }

    public function testLoadWithContainerBindings(): void
    {
        Container::getGlobalContainer()
            ->singleton(MyServiceInterface::class, MyFacadeAwareClass::class)
            ->singleton(MyServiceClass::class, MyFacadeAwareClass::class);
        $instance1 = MyInterfaceFacade::getInstance();
        $instance2 = MyBrokenFacade::getInstance();
        $instance3 = MyClassFacade::getInstance();
        $this->assertInstanceOf(MyFacadeAwareClass::class, $instance1);
        $this->assertSame($instance1, $instance2);
        $this->assertNotSame($instance1, $instance3);
    }

    public function testLoadWithInvalidBinding(): void
    {
        $container = Container::getGlobalContainer();
        $container->bind(MyServiceInterface::class, stdClass::class);
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage(' does not inherit ');
        MyInterfaceFacade::load();
    }

    public function testGlobalContainerIsSetAndUnloaded(): void
    {
        $this->assertFalse(Container::hasGlobalContainer());
        $this->assertFalse(App::isLoaded());
        App::get(stdClass::class);
        $this->assertTrue(Container::hasGlobalContainer());
        $this->assertTrue(App::isLoaded());
        $container = Container::getGlobalContainer();
        $this->assertSame($container, App::getInstance());
        App::unload();
        $this->assertFalse(Container::hasGlobalContainer());
        $this->assertFalse(App::isLoaded());
    }

    public function testGlobalContainerIsNotReplaced(): void
    {
        $container = Container::getGlobalContainer();
        $this->assertFalse($container->has(MyServiceClass::class));
        MyClassFacade::load();
        $this->assertTrue($container->has(MyServiceClass::class));
        $this->assertFalse(App::isLoaded());
        App::get(stdClass::class);
        $this->assertTrue(App::isLoaded());
        $this->assertSame($container, App::getInstance());
        App::unload();
        $this->assertFalse(Container::hasGlobalContainer());
        $this->assertFalse(App::isLoaded());
        $this->assertFalse($container->has(MyServiceClass::class));
    }

    public function testGlobalContainerBindingsAreMaintained(): void
    {
        MyClassFacade::load();
        $this->assertFalse(Container::hasGlobalContainer());
        $container = Container::getGlobalContainer();
        $container2 = new Container();
        $this->assertTrue($container->has(MyServiceClass::class));
        $this->assertFalse($container2->has(MyServiceClass::class));

        $this->assertFalse(App::isLoaded());
        App::get(stdClass::class);
        $this->assertTrue(App::isLoaded());
        $this->assertSame($container, App::getInstance());

        Container::setGlobalContainer($container2);
        $this->assertTrue(App::isLoaded());
        $this->assertSame($container2, App::getInstance());
        $this->assertTrue($container2->has(MyServiceClass::class));
        $this->assertFalse($container->has(MyServiceClass::class));
    }

    protected function tearDown(): void
    {
        Facade::unloadAll();
        if (Container::hasGlobalContainer()) {
            Container::getGlobalContainer()->unload();
        }
        MyFacadeAwareInstanceClass::reset();
        MyServiceClass::reset();
        MyFacadeAwareClass::reset();
    }
}
