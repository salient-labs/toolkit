<?php declare(strict_types=1);

namespace Lkrms\Facade;

use Lkrms\Concept\Facade;
use Lkrms\Concept\FluentInterface;
use Lkrms\Container\Container;
use Lkrms\Container\ServiceLifetime;
use Lkrms\Contract\IContainer;
use Lkrms\Contract\IService;

/**
 * A facade for \Lkrms\Container\Container
 *
 * @method static Container load() Load and return an instance of the underlying Container class
 * @method static Container getInstance() Get the underlying Container instance
 * @method static bool isLoaded() True if an underlying Container instance has been loaded
 * @method static void unload() Clear the underlying Container instance
 * @method static Container apply(callable($this): $this $callback) Move to the next method in the chain after applying a callback to the object
 * @method static Container bind(class-string $id, class-string|null $instanceOf = null, mixed[]|null $constructParams = null, class-string[]|null $shareInstances = null) Register a binding with the container (see {@see Container::bind()})
 * @method static Container bindIf(class-string $id, class-string|null $instanceOf = null, mixed[]|null $constructParams = null, class-string[]|null $shareInstances = null) Register a binding with the container if it isn't already registered
 * @method static mixed get(class-string $id, mixed[] $args = []) Get an object from the container (see {@see Container::get()})
 * @method static mixed getAs(class-string $id, class-string $service, mixed[] $args = []) Get an object from the container with a given service name (see {@see Container::getAs()})
 * @method static class-string[] getContextStack() Get services for which contextual bindings have been applied to the container (see {@see Container::getContextStack()})
 * @method static IContainer getGlobalContainer() Get the global container, creating it if necessary
 * @method static class-string getName(class-string $id) Resolve a service to a concrete class name (see {@see Container::getName()})
 * @method static array<class-string<IService>> getServices() Get a list of classes bound to the container by calling service()
 * @method static bool has(class-string $id) True if the container can resolve an identifier to an instance (see {@see Container::has()})
 * @method static bool hasGlobalContainer() True if the global container exists
 * @method static Container if((callable($this): bool)|bool $condition, (callable($this): $this)|null $then = null, (callable($this): $this)|null $else = null) Move to the next method in the chain after applying a conditional callback to the object (see {@see FluentInterface::if()})
 * @method static Container inContextOf(class-string $id) Apply the contextual bindings of a service to a copy of the container
 * @method static Container instance(class-string $id, mixed $instance) Register an existing instance with the container as a shared binding
 * @method static Container instanceIf(class-string $id, mixed $instance) Register an existing instance with the container as a shared binding if it isn't already registered
 * @method static IContainer|null maybeGetGlobalContainer() Get the global container if it exists
 * @method static IContainer requireGlobalContainer() Get the global container if it exists, otherwise throw an exception (see {@see Container::requireGlobalContainer()})
 * @method static Container service(class-string<IService> $id, class-string[]|null $services = null, class-string[]|null $exceptServices = null, int-mask-of<ServiceLifetime::*> $lifetime = ServiceLifetime::INHERIT) Register an IService with the container, optionally specifying services to include or exclude (see {@see Container::service()})
 * @method static Container services(array<class-string|int,class-string<IService>> $serviceMap, int-mask-of<ServiceLifetime::*> $lifetime = ServiceLifetime::INHERIT) Register a service map with the container (see {@see Container::services()})
 * @method static IContainer|null setGlobalContainer(IContainer|null $container) Set the global container
 * @method static Container singleton(class-string $id, class-string|null $instanceOf = null, mixed[]|null $constructParams = null, class-string[]|null $shareInstances = null) Register a shared binding with the container (see {@see Container::singleton()})
 * @method static Container singletonIf(class-string $id, class-string|null $instanceOf = null, mixed[]|null $constructParams = null, class-string[]|null $shareInstances = null) Register a shared binding with the container if it isn't already registered
 * @method static Container unbind(class-string $id) Remove a binding from the container
 *
 * @uses Container
 *
 * @extends Facade<Container>
 */
final class DI extends Facade
{
    /**
     * @inheritDoc
     */
    protected static function getServiceName(): string
    {
        return Container::class;
    }
}
