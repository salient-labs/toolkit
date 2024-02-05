<?php declare(strict_types=1);

namespace Lkrms\Tests\Container;

use Lkrms\Concept\FluentInterface;
use Lkrms\Container\Contract\ApplicationInterface;
use Lkrms\Container\Contract\ContainerInterface;
use Lkrms\Container\Exception\ContainerServiceNotFoundException;
use Lkrms\Container\Application;
use Lkrms\Container\Container;
use Lkrms\Container\ServiceLifetime;
use Lkrms\Contract\IFluentInterface;
use Lkrms\Tests\TestCase;
use Psr\Container\ContainerInterface as PsrContainerInterface;

final class ContainerTest extends TestCase
{
    public function testBindContainer(): void
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
     */
    public function testOnlyBindsContainer(string $id): void
    {
        $container = new Container();
        $this->assertFalse($container->has($id));

        if (interface_exists($id)) {
            $this->expectException(ContainerServiceNotFoundException::class);
            $container->get($id);
        }
    }

    /**
     * @return array<array{string}>
     */
    public static function onlyBindsContainerProvider(): array
    {
        return [
            [IFluentInterface::class],
            [FluentInterface::class],
            [ApplicationInterface::class],
            [Application::class],
        ];
    }

    public function testServiceTransient(): void
    {
        $container = (new Container())->provider(TestServiceImplA::class, null, [], ServiceLifetime::TRANSIENT);
        $this->_testServiceTransient($container);
    }

    public function testServiceSingleton(): void
    {
        $container = (new Container())->provider(TestServiceImplA::class, null, [], ServiceLifetime::SINGLETON);
        $this->_testServiceSingleton($container);
    }

    public function testServiceInherit(): void
    {
        $container = (new Container())->provider(TestServiceImplA::class);
        $this->_testServiceTransient($container);

        $container = (new Container())->provider(TestServiceImplB::class);
        $this->_testServiceSingleton($container, TestServiceImplB::class);
    }

    public function testServiceBindings(): void
    {
        $container = (new Container())->provider(TestServiceImplB::class);
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

    public function testGetAs(): void
    {
        $container = (new Container())->provider(TestServiceImplB::class);

        $o1 = $container->get(C::class);
        $this->assertInstanceOf(C::class, $o1);
        $this->assertSame(C::class, $o1->service());
        $this->assertInstanceOf(A::class, $o1->a);
        $this->assertNotInstanceOf(B::class, $o1->a);

        $o2 = $container->getAs(C::class, D::class);
        $this->assertInstanceOf(C::class, $o2);
        $this->assertNotInstanceOf(D::class, $o2);
        $this->assertSame(D::class, $o2->service());
        $this->assertInstanceOf(A::class, $o2->a);
        $this->assertNotInstanceOf(B::class, $o2->a);

        $o3 = $container->get(A::class);
        $this->assertInstanceOf(A::class, $o3);
        $this->assertNotInstanceOf(B::class, $o3);
        $this->assertSame(A::class, $o3->service());

        $o4 = $container->getAs(A::class, B::class);
        $this->assertInstanceOf(A::class, $o4);
        $this->assertNotInstanceOf(B::class, $o4);
        $this->assertSame(B::class, $o4->service());

        $ts1 = $container->get(ITestService1::class);
        $container2 = $container->inContextOf(get_class($ts1));

        $o5 = $container2->get(C::class);
        $this->assertInstanceOf(C::class, $o5);
        $this->assertSame(C::class, $o5->service());
        $this->assertInstanceOf(B::class, $o5->a);

        $o6 = $container2->getAs(C::class, D::class);
        $this->assertInstanceOf(C::class, $o6);
        $this->assertNotInstanceOf(D::class, $o6);
        $this->assertSame(D::class, $o6->service());
        $this->assertInstanceOf(B::class, $o6->a);

        $o7 = $container2->get(A::class);
        $this->assertInstanceOf(B::class, $o7);
        $this->assertSame(A::class, $o7->service());

        $o8 = $container2->getAs(A::class, B::class);
        $this->assertInstanceOf(B::class, $o8);
        $this->assertSame(B::class, $o8->service());
    }

    private function _testServiceTransient(Container $container, string $concrete = TestServiceImplA::class): void
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

    private function _testServiceSingleton(Container $container, string $concrete = TestServiceImplA::class): void
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

    public function testRegisterServiceProvider(): void
    {
        $container = new Container();
        $this->assertFalse($container->hasProvider(ServiceProviderPlain::class));
        $container->provider(ServiceProviderPlain::class);
        $this->assertTrue($container->hasProvider(ServiceProviderPlain::class));
        $this->assertSame([ServiceProviderPlain::class], $container->getProviders());
        $this->assertFalse($container->has(ServiceProviderPlain::class));

        $container = new Container();
        $container->provider(ServiceProviderWithBindings::class);
        $this->assertSame([ServiceProviderWithBindings::class], $container->getProviders());
        $this->assertFalse($container->has(ServiceProviderWithBindings::class));
        $this->assertTrue($container->has(User::class));
        $this->assertTrue($container->has(Staff::class));
        $this->assertFalse($container->has(DepartmentStaff::class));
        $this->assertTrue($container->has(IdGenerator::class));
        $this->assertFalse($container->hasInstance(IdGenerator::class));
        $generator = $container->get(IdGenerator::class);
        $this->assertTrue($container->hasInstance(IdGenerator::class));
        $this->assertSame($generator, $container->get(IdGenerator::class));

        $container = new Container();
        $container->provider(ServiceProviderWithContextualBindings::class);
        $this->assertSame([ServiceProviderWithContextualBindings::class], $container->getProviders());
        $this->assertTrue($container->has(ServiceProviderWithContextualBindings::class));
        $this->assertFalse($container->has(Office::class));
        $this->assertFalse($container->has(User::class));
        $this->assertFalse($container->has(Staff::class));
        $this->assertFalse($container->has(FancyOffice::class));
        $this->assertFalse($container->has(DepartmentStaff::class));

        $container = $container->inContextOf(ServiceProviderWithContextualBindings::class);
        $this->assertTrue($container->has(Office::class));
        $this->assertTrue($container->has(User::class));
        $this->assertTrue($container->has(Staff::class));
        $this->assertFalse($container->has(FancyOffice::class));
        $this->assertFalse($container->has(DepartmentStaff::class));
    }

    public function testObjectTree(): void
    {
        $container = new Container();

        $container->singleton(IdGenerator::class);

        // Give 'Office' instances a sequential name
        $offices = 0;
        $container->addContextualBinding(
            Office::class,
            '$name',
            function () use (&$offices) {
                return 'Office #' . (++$offices);
            },
        );

        // Create one `Office` per `User` and `Department`
        $office = null;
        $container->addContextualBinding(
            [User::class, Department::class],
            Office::class,
            function (ContainerInterface $container) use (&$office): Office {
                return $office ??= $container->get(Office::class);
            }
        );

        // Give each `Department` the same name
        $container->bind(Department::class, null, ['They Who Shall Not Be Named']);

        // Register `OrgUnit` bindings
        $container->provider(OrgUnit::class);

        // Create one `Department` per `OrgUnit` and `User`
        $department = null;
        $container->addContextualBinding(
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

        $office = null;
        $department = null;
        $user = $container->get(DepartmentStaff::class);
        $office = null;
        $department = null;
        $user = $container->get(DepartmentStaff::class);
        $this->assertSame(DepartmentStaff::class, get_class($user));
        /** @var DepartmentStaff $user */
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
        $this->assertInstanceOf(Department::class, $dept1);
        $this->assertInstanceOf(Department::class, $dept2);
        $this->assertNotSame($dept1, $dept2);
        $this->assertSame($dept1->MainOffice, $dept2->MainOffice);
        $this->assertSame(303, $dept1->Id);
        $this->assertSame('English', $dept1->Name);
        $this->assertSame(105, $dept1->MainOffice->Id);
        $this->assertSame('Office #6', $dept1->MainOffice->Name);
        $this->assertSame(304, $dept2->Id);
        $this->assertSame('Mathematics', $dept2->Name);

        $office = null;
        $department = null;
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
        $this->assertInstanceOf(FancyOffice::class, $orgUnit->MainOffice);
        $this->assertNotInstanceOf(FancyOffice::class, $manager->Office);
        $this->assertSame($manager->Office, $admin->Office);
    }
}
