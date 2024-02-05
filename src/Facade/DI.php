<?php declare(strict_types=1);

namespace Lkrms\Facade;

use Lkrms\Concept\Facade;
use Lkrms\Concept\FluentInterface;
use Lkrms\Container\Contract\ContainerInterface;
use Lkrms\Container\Container;
use Lkrms\Container\ServiceLifetime;

/**
 * A facade for Container
 *
 * @method static Container addContextualBinding(class-string $class, class-string|string $dependency, (callable($this): mixed)|class-string|mixed $value) Register a contextual binding with the container (see {@see Container::addContextualBinding()})
 * @method static Container apply(callable($this): $this $callback) Move to the next method in the chain after applying a callback to the object
 * @method static Container bind(class-string $id, class-string|null $class = null, mixed[] $args = [], class-string[] $shared = []) Register a binding with the container (see {@see Container::bind()})
 * @method static Container bindIf(class-string $id, class-string|null $class = null, mixed[] $args = [], class-string[] $shared = []) Register a binding with the container if it isn't already registered
 * @method static object get(class-string $id, mixed[] $args = []) Resolve a service from the container (see {@see Container::get()})
 * @method static object getAs(class-string $id, class-string $service, mixed[] $args = []) Resolve a partially-resolved service from the container (see {@see Container::getAs()})
 * @method static ContainerInterface getGlobalContainer() Get the global container, creating it if necessary
 * @method static class-string getName(class-string $id) Resolve a service from the container to a concrete class name
 * @method static array<class-string> getProviders() Get a list of service providers registered with the container
 * @method static bool has(class-string $id) True if a service has been bound to the container (see {@see Container::has()})
 * @method static bool hasGlobalContainer() True if the global container is set
 * @method static bool hasInstance(class-string $id) True if a service resolves to a shared instance
 * @method static Container if((callable($this): bool)|bool $condition, (callable($this): $this)|null $then = null, (callable($this): $this)|null $else = null) Move to the next method in the chain after applying a conditional callback to the object (see {@see FluentInterface::if()})
 * @method static Container inContextOf(class-string $id) Apply the contextual bindings of a service to a copy of the container
 * @method static Container instance(class-string $id, object $instance) Register an object with the container as a shared binding
 * @method static Container instanceIf(class-string $id, object $instance) Register an object with the container as a shared binding if it isn't already registered
 * @method static ContainerInterface|null maybeGetGlobalContainer() Get the global container if set
 * @method static Container provider(class-string $id, class-string[]|null $services = null, class-string[] $exceptServices = [], int-mask-of<ServiceLifetime::*> $lifetime = ServiceLifetime::INHERIT) Register a service provider with the container, optionally specifying which of its services to bind or ignore (see {@see Container::provider()})
 * @method static Container providers(array<class-string,class-string> $serviceMap, int-mask-of<ServiceLifetime::*> $lifetime = ServiceLifetime::INHERIT) Register a service map with the container (see {@see Container::providers()})
 * @method static ContainerInterface requireGlobalContainer() Get the global container if set, otherwise throw an exception (see {@see Container::requireGlobalContainer()})
 * @method static void setGlobalContainer(?ContainerInterface $container) Set or unset the global container
 * @method static Container singleton(class-string $id, class-string|null $class = null, mixed[] $args = [], class-string[] $shared = []) Register a shared binding with the container (see {@see Container::singleton()})
 * @method static Container singletonIf(class-string $id, class-string|null $class = null, mixed[] $args = [], class-string[] $shared = []) Register a shared binding with the container if it isn't already registered
 * @method static Container unbind(class-string $id) Remove a binding from the container
 *
 * @extends Facade<Container>
 *
 * @generated
 */
final class DI extends Facade
{
    /**
     * @inheritDoc
     */
    protected static function getService()
    {
        return Container::class;
    }
}
