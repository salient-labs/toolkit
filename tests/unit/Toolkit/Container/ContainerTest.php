<?php declare(strict_types=1);

namespace Salient\Tests\Container;

use Psr\Container\ContainerInterface as PsrContainerInterface;
use Psr\Log\LoggerInterface as PsrLoggerInterface;
use Salient\Container\Application;
use Salient\Container\Container;
use Salient\Contract\Console\ConsoleInterface;
use Salient\Contract\Container\Event\BeforeGlobalContainerSetEvent;
use Salient\Contract\Container\Exception\InvalidServiceException;
use Salient\Contract\Container\Exception\ServiceNotFoundException;
use Salient\Contract\Container\Exception\UnusedArgumentsException;
use Salient\Contract\Container\ApplicationInterface;
use Salient\Contract\Container\ContainerAwareInterface;
use Salient\Contract\Container\ContainerInterface;
use Salient\Contract\Container\HasBindings;
use Salient\Contract\Container\HasContainer;
use Salient\Contract\Container\HasContextualBindings;
use Salient\Contract\Container\HasServices;
use Salient\Contract\Container\ServiceAwareInterface;
use Salient\Contract\Container\SingletonInterface;
use Salient\Contract\Core\Facade\FacadeAwareInterface;
use Salient\Contract\Core\Chainable;
use Salient\Contract\Core\Instantiable;
use Salient\Contract\Core\Unloadable;
use Salient\Core\Facade\App;
use Salient\Core\Facade\Event;
use Salient\Tests\TestCase;
use Closure;
use InvalidArgumentException;
use LogicException;
use stdClass;

/**
 * @covers \Salient\Container\Container
 */
final class ContainerTest extends TestCase
{
    protected function tearDown(): void
    {
        if (Event::isLoaded()) {
            Event::unload();
        }
    }

    public function testGlobalContainer(): void
    {
        $setCount = 0;
        $unsetCount = 0;
        Event::getInstance()->listen(
            function (BeforeGlobalContainerSetEvent $event) use (&$setCount, &$unsetCount) {
                if ($event->getContainer()) {
                    $setCount++;
                } else {
                    $unsetCount++;
                }
            }
        );
        $this->assertFalse(Container::hasGlobalContainer());
        $container = Container::getGlobalContainer();
        $this->assertTrue(Container::hasGlobalContainer());
        $this->assertSame($container, Container::getGlobalContainer());
        Container::setGlobalContainer(null);
        Container::setGlobalContainer(null);
        $this->assertFalse(Container::hasGlobalContainer());
        Container::setGlobalContainer($container = new Container());
        Container::setGlobalContainer($container);
        $this->assertTrue(Container::hasGlobalContainer());
        $this->assertSame($container, Container::getGlobalContainer());
        $container->unload();
        $this->assertFalse(Container::hasGlobalContainer());
        $this->assertSame(2, $setCount);
        $this->assertSame(2, $unsetCount);
    }

    public function testBindsContainer(): void
    {
        $container = new Container();
        $this->assertTrue($container->has(PsrContainerInterface::class));
        $this->assertTrue($container->has(ContainerInterface::class));
        $this->assertTrue($container->has(Container::class));
        $this->assertSame($container, $container->get(PsrContainerInterface::class));
        $this->assertSame($container, $container->get(ContainerInterface::class));
        $this->assertSame($container, $container->get(Container::class));
    }

    /**
     * @dataProvider onlyBindsContainerProvider
     *
     * @param class-string $id
     */
    public function testOnlyBindsContainer(string $id): void
    {
        $container = new Container();
        $this->assertFalse($container->has($id));
        if (interface_exists($id)) {
            $this->expectException(ServiceNotFoundException::class);
            $container->get($id);
        }
    }

    /**
     * @return array<array{class-string}>
     */
    public static function onlyBindsContainerProvider(): array
    {
        return [
            [Chainable::class],
            [Instantiable::class],
            [Unloadable::class],
            [FacadeAwareInterface::class],
            [ApplicationInterface::class],
            [Application::class],
        ];
    }

    public function testBindsClosures(): void
    {
        $next = 0;
        $closure = function () use (&$next) {
            $obj = new stdClass();
            $obj->Id = $next++;
            return $obj;
        };

        $container = (new Container())->bind(stdClass::class, $closure);
        $this->assertSame(0, $container->get(stdClass::class)->Id);
        $this->assertSame(1, $container->get(stdClass::class)->Id);

        $container = (new Container())->singleton(stdClass::class, $closure);
        $this->assertSame(2, ($s = $container->get(stdClass::class))->Id);
        $this->assertSame($s, $container->get(stdClass::class));
    }

    public function testInContextOf(): void
    {
        $container = new Container();
        $this->assertSame($container, $container->inContextOf(PlainProvider::class));
        $this->assertSame($container, $container->inContextOf(ProviderWithInterfaces::class));
    }

    public function testBindIf(): void
    {
        $container = (new Container())
            ->provider(Provider2::class)
            ->bindIf(A::class, B::class);
        $this->assertInstanceOf(B::class, $container->get(A::class));
        $container->bindIf(A::class);
        $this->assertInstanceOf(B::class, $container->get(A::class));
        $container->bind(A::class);
        // @phpstan-ignore method.impossibleType
        $this->assertNotInstanceOf(B::class, $container->get(A::class));
    }

    public function testSingletonIf(): void
    {
        $container = (new Container())
            ->provider(Provider2::class)
            ->singletonIf(A::class, B::class);
        $this->assertInstanceOf(B::class, $a = $container->get(A::class));
        $this->assertSame($a, $container->get(A::class));
        $container->singletonIf(A::class);
        $this->assertSame($a, $container->get(A::class));
        $container->singleton(A::class);
        // @phpstan-ignore method.impossibleType
        $this->assertNotInstanceOf(B::class, $container->get(A::class));
    }

    public function testDefaultServices(): void
    {
        $container = new Container();
        $console = $container->get(ConsoleInterface::class);
        $l = $container->get(L::class);
        $this->assertInstanceOf(ConsoleInterface::class, $console);
        $this->assertInstanceOf(PsrLoggerInterface::class, $l->Logger);
    }

    public function testGetWithUnusedArguments(): void
    {
        $container = (new Container())->singleton(stdClass::class);
        $container->get(stdClass::class);
        $this->expectException(UnusedArgumentsException::class);
        $this->expectExceptionMessage('Cannot apply arguments to shared instance: stdClass');
        $container->get(stdClass::class, ['foo' => 'bar']);
    }

    public function testGetServiceAwareInterface(): void
    {
        $container = (new Container())
            ->provider(Provider2::class)
            ->singleton(A::class, B::class);
        $a1 = $container->get(A::class);
        $a2 = $container->get(A::class);
        $b1 = $container->get(B::class);
        $b2 = $container->get(B::class);
        $this->assertInstanceOf(B::class, $a1);
        $this->assertSame($a1, $a2);
        $this->assertNotSame($a2, $b1);
        $this->assertNotSame($b1, $b2);
        $this->assertSame(2, $a1->getSetServiceCount());
        $this->assertSame(1, $b1->getSetServiceCount());
    }

    public function testGetProviderWithServices(): void
    {
        $container = (new Container())->provider(Provider1::class);
        $this->doTestGetTransientProvider(
            $container,
            Provider1::class,
            Service1::class,
            Service2::class,
        );

        $container = (new Container())->provider(Provider2::class);
        $this->assertFalse($container->has(Service3::class));
        $container->singleton(Service3::class, Provider2::class);
        $this->assertTrue($container->has(Service3::class));
        $this->doTestGetSingletonProvider(
            $container,
            Provider2::class,
            Service1::class,
            Service2::class,
            Service3::class,
        );

        $container = (new Container())->provider(Provider2::class, [Service1::class]);
        $this->doTestGetSingletonProvider(
            $container,
            Provider2::class,
            Service1::class,
        );
        $this->assertFalse($container->has(Service2::class));

        $container = (new Container())->provider(Provider2::class, null, [Service1::class]);
        $this->doTestGetSingletonProvider(
            $container,
            Provider2::class,
            Service2::class,
        );
        $this->assertFalse($container->has(Service1::class));

        foreach ([Provider1::class, Provider2::class] as $id) {
            $this->doTestGetTransientProvider(
                (new Container())
                    ->provider($id, null, [], Container::LIFETIME_TRANSIENT),
                $id,
                Service1::class,
                Service2::class,
            );
        }

        foreach ([Provider1::class, Provider2::class] as $id) {
            $this->doTestGetSingletonProvider(
                (new Container())
                    ->provider($id, null, [], Container::LIFETIME_SINGLETON),
                $id,
                Service1::class,
                Service2::class,
            );
        }

        $this->expectException(InvalidServiceException::class);
        $this->expectExceptionMessage(sprintf(
            '%s does not implement: %s',
            Provider2::class,
            Service3::class,
        ));
        (new Container())->provider(Provider2::class, [Service3::class]);
    }

    /**
     * @param class-string ...$id
     */
    private function doTestGetTransientProvider(Container $container, string ...$id): void
    {
        foreach ($id as $id) {
            $first = $container->get($id);
            $second = $container->get($id);
            $this->assertNotSame($first, $second);
        }
    }

    /**
     * @param class-string ...$id
     */
    private function doTestGetSingletonProvider(Container $container, string ...$id): void
    {
        $prev = null;
        foreach ($id as $id) {
            $first = $container->get($id);
            $second = $container->get($id);
            $this->assertSame($first, $second);
            if ($prev) {
                $this->assertSame($prev, $first);
            }
            $prev = $first;
        }
    }

    public function testGetProviderWithContextualBindings(): void
    {
        $container = (new Container())->provider(Provider2::class);
        $service1 = $container->get(Service1::class);
        $container2 = $container->inContextOf(Provider2::class);
        $container3 = $container2->inContextOf(Provider2::class);
        $a1 = $container->get(A::class);
        $a2 = $container2->get(A::class);
        $s1 = $container->get(stdClass::class);
        $s2 = $container2->get(stdClass::class);

        $this->assertNotSame($container, $container2);
        $this->assertSame($container2, $container3);
        $this->assertInstanceOf(A::class, $a1);
        $this->assertNotInstanceOf(B::class, $a1);
        $this->assertInstanceOf(B::class, $a2);
        $this->assertEquals(new stdClass(), $s1);
        $this->assertSame(Provider2::class . '::getContextualBindings()', $s2->From);

        $this->assertSame($service1, $a1->Service1);
        $this->assertSame($service1, $a2->Service1);
        $this->assertSame($container, $a1->getContainer());
        $this->assertSame($container2, $a2->getContainer());
        $this->assertSame(A::class, $a1->getService());
        $this->assertSame(A::class, $a2->getService());

        $container = (new Container())->provider(ProviderWithContextualBindings::class);
        $provider = $container->get(ProviderWithContextualBindings::class);
        $this->assertSame(ProviderWithContextualBindings::class . '::getContextualBindings()', $provider->Object->From);
        $this->assertSame(71, $provider->Scalar);
        $this->assertSame('instance', $provider->Instance->From);

        // `ProviderB` is only bound to itself, so the container can't inject
        // `Service1` into `A::__construct()` unless it's passed as a parameter
        $container = (new Container())->inContextOf(Provider2::class);
        $service1 = $container->get(Provider2::class);
        $a3 = $container->get(A::class, [$service1]);
        $this->assertInstanceOf(B::class, $a3);

        $this->expectException(ServiceNotFoundException::class);
        $container->get(A::class);
    }

    public function testGetAs(): void
    {
        $container = (new Container())
            ->provider(Provider2::class)
            ->bind(D::class, E::class);
        $container2 = $container->inContextOf(Provider2::class);

        $c = $container->get(C::class);
        $this->assertInstanceOf(C::class, $c);
        $this->assertSame(C::class, $c->getService());
        $this->assertInstanceOf(A::class, $c->A);
        $this->assertNotInstanceOf(B::class, $c->A);

        $d = $container->getAs(D::class, C::class);
        $this->assertInstanceOf(E::class, $d);
        $this->assertSame(C::class, $d->getService());
        $this->assertInstanceOf(A::class, $d->A);
        $this->assertNotInstanceOf(B::class, $d->A);

        $a = $container->get(A::class);
        $this->assertInstanceOf(A::class, $a);
        $this->assertNotInstanceOf(B::class, $a);
        $this->assertSame(A::class, $a->getService());

        $b = $container->getAs(B::class, A::class);
        $this->assertInstanceOf(B::class, $b);
        $this->assertSame(A::class, $b->getService());

        $c2 = $container2->get(C::class);
        $this->assertInstanceOf(C::class, $c2);
        $this->assertSame(C::class, $c2->getService());
        $this->assertInstanceOf(B::class, $c2->A);

        $d2 = $container2->getAs(D::class, C::class);
        $this->assertInstanceOf(D::class, $d2);
        $this->assertNotInstanceOf(E::class, $d2);
        $this->assertSame(C::class, $d2->getService());
        $this->assertInstanceOf(B::class, $d2->A);

        $a2 = $container2->get(A::class);
        $this->assertInstanceOf(B::class, $a2);
        $this->assertSame(A::class, $a2->getService());

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(A::class . ' does not inherit ' . Service3::class);
        // @phpstan-ignore salient.service.type
        $container2->getAs(A::class, Service3::class);
    }

    public function testGetName(): void
    {
        $container = (new Container())->provider(Provider2::class);
        $this->assertSame(Provider2::class, $container->getClass(Service1::class));
        $this->assertFalse($container->hasInstance(Service1::class));
        $this->assertSame(C::class, $container->getClass(C::class));

        $container->instance(C::class, $container->get(D::class));
        $this->assertSame(D::class, $container->getClass(C::class));
    }

    public function testProvider(): void
    {
        $container = new Container();
        $this->assertFalse($container->hasProvider(PlainProvider::class));
        $container->provider(PlainProvider::class);
        $this->assertTrue($container->hasProvider(PlainProvider::class));
        $this->assertSame([PlainProvider::class], $container->getProviders());
        $this->assertFalse($container->has(PlainProvider::class));

        $container = new Container();
        $container->provider(ProviderWithInterfaces::class);
        $this->assertTrue($container->hasProvider(ProviderWithInterfaces::class));
        $this->assertSame([ProviderWithInterfaces::class], $container->getProviders());
        $this->assertTrue($container->has(ProviderWithInterfaces::class));
        $this->assertTrue($container->has(Service1::class));
        $this->assertTrue($container->has(Service2::class));
        $this->assertFalse($container->has(Service3::class));
        $this->assertFalse($container->has(SingletonInterface::class));

        $container = new Container();
        $container->provider(ProviderWithBindings::class);
        $this->assertSame([ProviderWithBindings::class], $container->getProviders());
        $this->assertFalse($container->has(ProviderWithBindings::class));
        $this->assertTrue($container->has(User::class));
        $this->assertTrue($container->has(Staff::class));
        $this->assertFalse($container->has(DepartmentStaff::class));
        $this->assertTrue($container->has(IdGenerator::class));
        $this->assertFalse($container->hasInstance(IdGenerator::class));
        $generator = $container->get(IdGenerator::class);
        $this->assertTrue($container->hasInstance(IdGenerator::class));
        $this->assertSame($generator, $container->get(IdGenerator::class));

        $container = new Container();
        $container->provider(ProviderWithContextualBindings::class);
        $this->assertSame([ProviderWithContextualBindings::class], $container->getProviders());
        $this->assertTrue($container->has(ProviderWithContextualBindings::class));
        $this->assertFalse($container->has(Office::class));
        $this->assertFalse($container->has(User::class));
        $this->assertFalse($container->has(Staff::class));
        $this->assertFalse($container->has(PhysicalOffice::class));
        $this->assertFalse($container->has(DepartmentStaff::class));

        $container = $container->inContextOf(ProviderWithContextualBindings::class);
        $this->assertTrue($container->has(Office::class));
        $this->assertTrue($container->has(User::class));
        $this->assertTrue($container->has(Staff::class));
        $this->assertFalse($container->has(PhysicalOffice::class));
        $this->assertFalse($container->has(DepartmentStaff::class));
    }

    /**
     * @dataProvider providerWithInvalidBindingsProvider
     *
     * @param class-string $provider
     */
    public function testProviderWithInvalidBindings(
        string $expectedMessage,
        string $provider
    ): void {
        $this->expectException(InvalidServiceException::class);
        $this->expectExceptionMessage($expectedMessage);
        (new Container())->provider($provider);
    }

    /**
     * @return array<array{string,class-string}>
     */
    public static function providerWithInvalidBindingsProvider(): array
    {
        return [
            [
                'Unmapped services must be of type class-string: ' . ProviderWithInvalidBindings::class . '::getSingletons()',
                ProviderWithInvalidBindings::class,
            ],
            [
                'Unmapped services must be of type class-string: ' . ProviderWithInvalidContextualBindings::class . '::getContextualBindings()',
                ProviderWithInvalidContextualBindings::class,
            ],
        ];
    }

    /**
     * @dataProvider providersProvider
     *
     * @param string|Closure(Container): mixed $callback
     * @param array<class-string|int,class-string> $providers
     * @param Container::LIFETIME_* $providerLifetime
     */
    public function testProviders(
        $callback,
        array $providers,
        int $providerLifetime = Container::LIFETIME_INHERIT
    ): void {
        $this->maybeExpectException($callback);
        $container = (new Container())->providers($providers, $providerLifetime);
        if ($callback instanceof Closure) {
            $callback($container);
        }
    }

    /**
     * @return array<array{string|Closure(Container): mixed,array<string|int,string>,2?:Container::LIFETIME_*}>
     */
    public static function providersProvider(): array
    {
        return [
            [
                function (Container $container) {
                    self::assertEqualsCanonicalizing([
                        Provider1::class,
                        Provider2::class,
                        PlainProvider::class,
                    ], $container->getProviders());
                    self::assertTrue($container->has(Service1::class));
                    self::assertFalse($container->has(Service2::class));
                    self::assertTrue($container->has(Provider1::class));
                    self::assertTrue($container->has(Provider2::class));
                    self::assertFalse($container->has(PlainProvider::class));
                },
                [
                    Service1::class => Provider2::class,
                    Provider1::class,
                    PlainProvider::class,
                ],
            ],
            [
                InvalidServiceException::class . ',' . Provider2::class . ' does not implement: ' . Service3::class,
                [Service3::class => Provider2::class],
            ],
            [
                InvalidArgumentException::class . ',Not a class: DoesNotExist',
                ['DoesNotExist'],
            ],
            [
                InvalidArgumentException::class . ',' . Provider2::class . ' does not inherit stdClass',
                [stdClass::class => Provider2::class],
            ],
        ];
    }

    public function testSharedInstances(): void
    {
        $container = new Container();
        $this->assertFalse($container->hasSingleton(stdClass::class));
        $this->assertFalse($container->hasInstance(stdClass::class));

        $container->bind(stdClass::class);
        $this->assertFalse($container->hasSingleton(stdClass::class));
        $this->assertFalse($container->hasInstance(stdClass::class));

        $container->instance(stdClass::class, new stdClass());
        $this->assertTrue($container->hasSingleton(stdClass::class));
        $this->assertTrue($container->hasInstance(stdClass::class));

        $container->removeInstance(stdClass::class);
        $this->assertFalse($container->hasSingleton(stdClass::class));
        $this->assertFalse($container->hasInstance(stdClass::class));

        $container->singleton(stdClass::class);
        $this->assertTrue($container->hasSingleton(stdClass::class));
        $this->assertFalse($container->hasInstance(stdClass::class));

        $container->get(stdClass::class);
        $this->assertTrue($container->hasSingleton(stdClass::class));
        $this->assertTrue($container->hasInstance(stdClass::class));

        $container->removeInstance(stdClass::class);
        $container->removeInstance(stdClass::class);
        $this->assertTrue($container->hasSingleton(stdClass::class));
        $this->assertFalse($container->hasInstance(stdClass::class));
    }

    public function testContextualInstances(): void
    {
        $container = (new Container())->singleton(IdGenerator::class);
        $office1 = $container->get(Office::class);
        $office2 = $container->get(Office::class);
        $container->addContextualBinding(Department::class, Office::class, $office1);
        $this->assertNotSame($office1, $container->get(Office::class));
        $this->assertSame($office1, $container->get(Department::class)->MainOffice);
        $this->assertSame($office1, $container->inContextOf(Department::class)->get(Office::class));

        $container->instance(Office::class, $office2);
        $this->assertSame($office2, $container->get(Office::class));
        $this->assertSame($office1, $container->get(Department::class)->MainOffice);
        $this->assertSame($office1, $container->inContextOf(Department::class)->get(Office::class));

        $container = (new Container())->singleton(IdGenerator::class);
        $office1 = $container->get(Office::class);
        $office2 = $container->get(Office::class);
        $container->addContextualBinding(Department::class, '$mainOffice', $office2);
        $container->addContextualBinding(Department::class, '$mainOffice', $office1);
        $container->addContextualBinding(Department::class, '$office', $office2);
        $this->assertNotSame($office1, $container->get(Office::class));
        $this->assertSame($office1, $container->get(Department::class)->MainOffice);
        $this->assertNotSame($office1, $container->inContextOf(Department::class)->get(Office::class));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('$class cannot be null when $id starts with \'$\'');
        $container->addContextualBinding(Department::class, '$name', null);
    }

    public function testContextualSingletons(): void
    {
        $offices = 0;
        $office = null;
        $department = null;
        $container = (new Container())
            ->singleton(IdGenerator::class)
            // Give 'Office' instances a sequential name
            ->addContextualBinding(
                Office::class,
                '$name',
                function () use (&$offices) {
                    return 'Office #' . (++$offices);
                },
            )
            // Create one `Office` per `User` and `Department`
            ->addContextualBinding(
                [User::class, Department::class],
                Office::class,
                function (ContainerInterface $container) use (&$office): Office {
                    return $office ??= $container->get(Office::class);
                }
            )
            // Give each `Department` the same name
            ->addContextualBinding(
                Department::class,
                '$name',
                fn() => 'They Who Shall Not Be Named',
            )
            // Register `OrgUnit` bindings
            ->provider(OrgUnit::class)
            // Create one `Department` per `OrgUnit` and `User`
            ->addContextualBinding(
                [OrgUnit::class, User::class],
                Department::class,
                function (ContainerInterface $container) use (&$department): Department {
                    return $department ??= $container->get(Department::class);
                }
            );

        $user = $container->get(User::class);
        $this->assertSame(User::class, get_class($user));
        $this->assertSame(100, $user->Office->Id);
        $this->assertSame('Office #1', $user->Office->Name);
        $this->assertSame(200, $user->Id);

        $office = null;
        $user = $container->inContextOf(OrgUnit::class)->get(User::class);
        $this->assertSame(DepartmentStaff::class, get_class($user));
        /** @var DepartmentStaff $user */
        $this->assertSame(101, $user->Office->Id);
        $this->assertSame('Office #2', $user->Office->Name);
        $this->assertSame(300, $user->Department->Id);
        $this->assertSame('They Who Shall Not Be Named', $user->Department->Name);
        $this->assertSame($user->Office, $user->Department->MainOffice);
        $this->assertSame(201, $user->Id);
        $this->assertSame(400, $user->StaffId);

        $office = null;
        $user = $container->get(User::class);
        $this->assertSame(User::class, get_class($user));
        $this->assertSame(102, $user->Office->Id);
        $this->assertSame('Office #3', $user->Office->Name);
        $this->assertSame(202, $user->Id);

        $office = $department = null;
        $user = $container->get(DepartmentStaff::class);
        $office = $department = null;
        $user = $container->get(DepartmentStaff::class);
        $this->assertSame(DepartmentStaff::class, get_class($user));
        $this->assertSame(104, $user->Office->Id);
        $this->assertSame('Office #5', $user->Office->Name);
        $this->assertSame(302, $user->Department->Id);
        $this->assertSame('They Who Shall Not Be Named', $user->Department->Name);
        $this->assertSame($user->Office, $user->Department->MainOffice);
        $this->assertSame(204, $user->Id);
        $this->assertSame(402, $user->StaffId);

        $office = null;
        $dept1 = $container->get(Department::class, ['English']);
        $dept2 = $container->get(Department::class, ['Mathematics', $dept1->MainOffice]);
        $this->assertSame(303, $dept1->Id);
        $this->assertSame('English', $dept1->Name);
        $this->assertSame(105, $dept1->MainOffice->Id);
        $this->assertSame('Office #6', $dept1->MainOffice->Name);
        $this->assertSame(304, $dept2->Id);
        $this->assertSame('Mathematics', $dept2->Name);
        $this->assertSame($dept1->MainOffice, $dept2->MainOffice);

        $office = $department = null;
        $orgUnit = $container->get(OrgUnit::class);
        $manager = $orgUnit->Manager;
        $admin = $orgUnit->Admin;
        $this->assertInstanceOf(OrgUnit::class, $orgUnit);
        $this->assertInstanceOf(DepartmentStaff::class, $manager);
        $this->assertInstanceOf(DepartmentStaff::class, $admin);
        /** @var DepartmentStaff $manager */
        $this->assertSame($orgUnit->Department, $manager->Department);
        /** @var DepartmentStaff $admin */
        $this->assertSame($orgUnit->Department, $admin->Department);
        $this->assertInstanceOf(PhysicalOffice::class, $orgUnit->MainOffice);
        $this->assertNotInstanceOf(PhysicalOffice::class, $manager->Office);
        $this->assertSame($manager->Office, $admin->Office);
    }

    public function testUnload(): void
    {
        $container = new Container();
        $container->bind(stdClass::class);
        $this->assertTrue($container->has(stdClass::class));
        $container->unload();
        $this->assertFalse($container->has(stdClass::class));
    }

    public function testUnloadsFacade(): void
    {
        $this->assertFalse(App::isLoaded());
        $this->assertFalse(Container::hasGlobalContainer());
        $container = App::getInstance();
        $this->assertTrue(App::isLoaded());
        $this->assertTrue(Container::hasGlobalContainer());
        $this->assertSame($container, Container::getGlobalContainer());
        $container->unload();
        $this->assertFalse(App::isLoaded());
        $this->assertFalse(Container::hasGlobalContainer());
    }
}

interface Service1 {}

interface Service2 {}

interface Service3 {}

class Provider1 implements Service1, Service2, HasServices, HasContextualBindings
{
    public static function getServices(): array
    {
        return [
            Service1::class,
            Service2::class,
        ];
    }

    public static function getContextualBindings(ContainerInterface $container): array
    {
        $from = sprintf('%s::%s()', static::class, __FUNCTION__);
        return [
            A::class => B::class,
            D::class,
            stdClass::class => function () use ($from) {
                $obj = new stdClass();
                $obj->From = $from;
                return $obj;
            },
        ];
    }
}

class Provider2 extends Provider1 implements Service3, SingletonInterface {}

class A implements ContainerAwareInterface, ServiceAwareInterface, HasContainer
{
    use TestTrait;

    public Service1 $Service1;

    public function __construct(Service1 $service1)
    {
        $this->Service1 = $service1;
    }
}

class B extends A {}

class C implements ContainerAwareInterface, ServiceAwareInterface, HasContainer
{
    use TestTrait;

    public A $A;

    public function __construct(A $a)
    {
        $this->A = $a;
    }
}

class D extends C {}

class E extends D {}

class L
{
    public PsrLoggerInterface $Logger;

    public function __construct(PsrLoggerInterface $logger)
    {
        $this->Logger = $logger;
    }
}

/**
 * @phpstan-require-implements ContainerAwareInterface
 * @phpstan-require-implements HasContainer
 * @phpstan-require-implements ServiceAwareInterface
 */
trait TestTrait
{
    protected ?ContainerInterface $Container = null;
    /** @var class-string|null */
    protected ?string $Service = null;
    protected int $SetServiceCount = 0;

    public function getContainer(): ContainerInterface
    {
        return $this->Container ??= new Container();
    }

    public function setContainer(ContainerInterface $container): void
    {
        if ($this->Container !== null) {
            throw new LogicException('Container already set');
        }
        $this->Container = $container;
    }

    public function getService(): string
    {
        return $this->Service ?? static::class;
    }

    public function setService(string $service): void
    {
        $this->Service = $service;
        $this->SetServiceCount++;
    }

    public function getSetServiceCount(): int
    {
        return $this->SetServiceCount;
    }
}

// --

class PlainProvider {}

class ProviderWithInterfaces implements Service1, Service2, SingletonInterface {}

class ProviderWithBindings implements HasBindings
{
    public static function getBindings(ContainerInterface $container): array
    {
        return [
            User::class => DepartmentStaff::class,
            Staff::class => DepartmentStaff::class,
        ];
    }

    public static function getSingletons(ContainerInterface $container): array
    {
        return [
            IdGenerator::class,
        ];
    }
}

class ProviderWithInvalidBindings implements HasBindings
{
    public static function getBindings(ContainerInterface $container): array
    {
        return [];
    }

    public static function getSingletons(ContainerInterface $container): array
    {
        return [
            fn() => new stdClass(),
        ];
    }
}

class ProviderWithContextualBindings implements HasContextualBindings
{
    public stdClass $Object;
    public int $Scalar;
    public stdClass $Instance;

    public function __construct(stdClass $object, int $scalar, stdClass $instance)
    {
        $this->Object = $object;
        $this->Scalar = $scalar;
        $this->Instance = $instance;
    }

    public static function getContextualBindings(ContainerInterface $container): array
    {
        $from = sprintf('%s::%s()', static::class, __FUNCTION__);
        return [
            Office::class => PhysicalOffice::class,
            User::class => DepartmentStaff::class,
            Staff::class => DepartmentStaff::class,
            '$scalar' => fn() => 71,
            '$object' => function () use ($from) {
                $obj = new stdClass();
                $obj->From = $from;
                return $obj;
            },
            '$instance' => new class extends stdClass { public string $From = 'instance'; },
        ];
    }
}

class ProviderWithInvalidContextualBindings implements HasContextualBindings
{
    public static function getContextualBindings(ContainerInterface $container): array
    {
        return [
            fn() => new stdClass(),
        ];
    }
}

class OrgUnit implements HasContextualBindings
{
    public Office $MainOffice;
    public Department $Department;
    public Staff $Manager;
    public User $Admin;

    public function __construct(Office $mainOffice, Department $department, Staff $manager, User $admin)
    {
        $this->MainOffice = $mainOffice;
        $this->Department = $department;
        $this->Manager = $manager;
        $this->Admin = $admin;
    }

    public static function getContextualBindings(ContainerInterface $container): array
    {
        return [
            Office::class => PhysicalOffice::class,
            User::class => DepartmentStaff::class,
            Staff::class => DepartmentStaff::class,
        ];
    }
}

class User
{
    public int $Id;
    public Office $Office;

    public function __construct(IdGenerator $idGenerator, Office $office)
    {
        $this->Id = $idGenerator->getNext(__CLASS__);
        $this->Office = $office;
    }
}

class Staff extends User
{
    public int $StaffId;

    public function __construct(IdGenerator $idGenerator, Office $office)
    {
        parent::__construct($idGenerator, $office);
        $this->StaffId = $idGenerator->getNext(__CLASS__);
    }
}

class DepartmentStaff extends Staff
{
    public Department $Department;

    public function __construct(IdGenerator $idGenerator, Office $office, Department $department)
    {
        parent::__construct($idGenerator, $office);
        $this->Department = $department;
    }
}

class Department
{
    public int $Id;
    public ?string $Name;
    public Office $MainOffice;

    public function __construct(IdGenerator $idGenerator, Office $mainOffice, ?string $name = null)
    {
        $this->Id = $idGenerator->getNext(__CLASS__);
        $this->Name = $name;
        $this->MainOffice = $mainOffice;
    }
}

class Office
{
    public int $Id;
    public ?string $Name;

    public function __construct(IdGenerator $idGenerator, ?string $name = null)
    {
        $this->Id = $idGenerator->getNext(__CLASS__);
        $this->Name = $name;
    }
}

class PhysicalOffice extends Office {}

class IdGenerator
{
    /** @var array<string,int> */
    private array $Counters = [];

    public function getNext(string $type): int
    {
        $this->Counters[$type] ??= 100 * (count($this->Counters) + 1);

        return $this->Counters[$type]++;
    }
}
