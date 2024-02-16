<?php declare(strict_types=1);

namespace Salient\Tests\Core;

use Lkrms\Container\Container;
use Lkrms\Facade\App;
use Lkrms\Facade\Event;
use Lkrms\Tests\TestCase;
use Salient\Core\AbstractFacade;
use Salient\Tests\Core\AbstractFacade\MyBrokenFacade;
use Salient\Tests\Core\AbstractFacade\MyClassFacade;
use Salient\Tests\Core\AbstractFacade\MyHasFacadeClass;
use Salient\Tests\Core\AbstractFacade\MyInterfaceFacade;
use Salient\Tests\Core\AbstractFacade\MyServiceClass;
use Salient\Tests\Core\AbstractFacade\MyServiceInterface;
use Salient\Tests\Core\AbstractFacade\MyUnloadsFacadesClass;
use LogicException;
use stdClass;

/**
 * @covers \Salient\Core\AbstractFacade
 * @covers \Salient\Core\Concern\HasFacade
 * @covers \Salient\Core\Concern\UnloadsFacades
 */
final class AbstractFacadeTest extends TestCase
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
        MyBrokenFacade::load(new MyUnloadsFacadesClass());
    }

    public function testLoadAndSwap(): void
    {
        $this->assertFalse(MyInterfaceFacade::isLoaded());
        MyInterfaceFacade::load();
        $this->assertTrue(MyInterfaceFacade::isLoaded());
        $this->assertSame([], MyInterfaceFacade::getArgs());
        $this->assertSame(1, MyInterfaceFacade::getClones());
        $instance = MyInterfaceFacade::getInstance();
        $this->assertInstanceOf(MyHasFacadeClass::class, $instance);
        $this->assertSame([], $instance->getArgs());
        $this->assertSame(0, $instance->getClones());

        $this->assertCount(0, MyHasFacadeClass::getUnloaded());
        MyInterfaceFacade::swap($instance);
        $this->assertCount(1, $unloaded = MyHasFacadeClass::getUnloaded());
        $this->assertInstanceOf(MyHasFacadeClass::class, $unloaded = reset($unloaded));
        $this->assertNotSame($instance, $unloaded);
        $this->assertSame(2, $unloaded->getClones());
        $this->assertSame($instance, MyInterfaceFacade::getInstance());

        MyInterfaceFacade::withArgs();
        $this->assertSame(1, MyInterfaceFacade::getClones());
        $this->assertSame($instance, MyInterfaceFacade::getInstance());

        MyInterfaceFacade::withArgs(__METHOD__, $line = __LINE__);
        $this->assertSame(2, MyInterfaceFacade::getClones());
        $this->assertNotSame($instance, MyInterfaceFacade::getInstance());
        $this->assertSame([__METHOD__, $line], MyInterfaceFacade::getArgs());

        $instance = new MyUnloadsFacadesClass(__METHOD__, $line = __LINE__);
        MyInterfaceFacade::swap($instance);
        $this->assertSame([__METHOD__, $line], MyInterfaceFacade::getArgs());
        $this->assertCount(3, MyHasFacadeClass::getUnloaded());
    }

    public function testLoadInvalidInstance(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage(' does not inherit ');
        // @phpstan-ignore-next-line
        MyClassFacade::load(new MyHasFacadeClass());
    }

    public function testSwapInvalidInstance(): void
    {
        MyClassFacade::load();
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage(' does not inherit ');
        // @phpstan-ignore-next-line
        MyClassFacade::swap(new MyHasFacadeClass());
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
        $this->assertInstanceOf(MyHasFacadeClass::class, $instance);
        /** @var MyHasFacadeClass $instance */
        $this->assertNotNull($instance->getInstanceWithFacade());
        $this->assertNotNull($instance->getInstanceWithoutFacade());
        MyInterfaceFacade::unload();
        $this->assertCount(2, $unloaded = MyHasFacadeClass::getUnloaded());
        $this->assertInstanceOf(MyHasFacadeClass::class, $unloaded = end($unloaded));
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
        $this->assertInstanceOf(MyHasFacadeClass::class, MyInterfaceFacade::getInstance());
        $this->assertInstanceOf(MyServiceClass::class, MyClassFacade::getInstance());
        $this->assertFalse($container->hasInstance(MyHasFacadeClass::class));
        $this->assertTrue($container->hasInstance(MyServiceInterface::class));
        $this->assertTrue($container->hasInstance(MyServiceClass::class));
    }

    public function testBrokenFacadeWithContainer(): void
    {
        Container::getGlobalContainer();
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Service not bound to container: ');
        MyBrokenFacade::load();
    }

    public function testLoadWithContainerBindings(): void
    {
        Container::getGlobalContainer()
            ->singleton(MyServiceInterface::class, MyUnloadsFacadesClass::class)
            ->singleton(MyServiceClass::class, MyUnloadsFacadesClass::class);
        $instance1 = MyInterfaceFacade::getInstance();
        $instance2 = MyBrokenFacade::getInstance();
        $instance3 = MyClassFacade::getInstance();
        $this->assertInstanceOf(MyUnloadsFacadesClass::class, $instance1);
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
        AbstractFacade::unloadAll();
        if (Container::hasGlobalContainer()) {
            Container::getGlobalContainer()->unload();
        }
        MyHasFacadeClass::reset();
        MyServiceClass::reset();
        MyUnloadsFacadesClass::reset();
    }
}
