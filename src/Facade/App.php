<?php declare(strict_types=1);

namespace Lkrms\Facade;

use Lkrms\Concept\Facade;
use Lkrms\Container\Contract\ContainerInterface;
use Lkrms\Container\Container;
use Lkrms\Container\ServiceLifetime;

/**
 * A facade for the global service container
 *
 * @method static ContainerInterface addContextualBinding(class-string[]|class-string $context, class-string|string $dependency, (callable($this): mixed)|class-string|mixed $value) Register a contextual binding with the container (see {@see ContainerInterface::addContextualBinding()})
 * @method static ContainerInterface bind(class-string $id, class-string|null $class = null, mixed[] $args = []) Register a binding with the container (see {@see ContainerInterface::bind()})
 * @method static ContainerInterface bindIf(class-string $id, class-string|null $class = null, mixed[] $args = []) Register a binding with the container if it isn't already registered
 * @method static object get(class-string $id, mixed[] $args = []) Resolve a service from the container (see {@see ContainerInterface::get()})
 * @method static object getAs(class-string $id, class-string $service, mixed[] $args = []) Resolve a partially-resolved service from the container (see {@see ContainerInterface::getAs()})
 * @method static ContainerInterface getGlobalContainer() Get the global container, creating it if necessary
 * @method static class-string getName(class-string $id) Resolve a service from the container to a concrete class name
 * @method static array<class-string> getProviders() Get a list of service providers registered with the container
 * @method static bool has(class-string $id) True if a service is bound to the container (see {@see ContainerInterface::has()})
 * @method static bool hasGlobalContainer() True if the global container is set
 * @method static bool hasInstance(class-string $id) True if a service resolves to a shared instance
 * @method static bool hasProvider(class-string $id) True if a service provider is registered with the container
 * @method static ContainerInterface inContextOf(class-string $id) Apply the contextual bindings of a service to a copy of the container
 * @method static ContainerInterface instance(class-string $id, object $instance) Register an object with the container as a shared binding
 * @method static ContainerInterface instanceIf(class-string $id, object $instance) Register an object with the container as a shared binding if it isn't already registered
 * @method static ContainerInterface provider(class-string $id, class-string[]|null $services = null, class-string[] $exceptServices = [], ServiceLifetime::* $lifetime = ServiceLifetime::INHERIT) Register a service provider with the container, optionally specifying which of its services to bind or ignore (see {@see ContainerInterface::provider()})
 * @method static ContainerInterface providers(array<class-string,class-string> $serviceMap, ServiceLifetime::* $lifetime = ServiceLifetime::INHERIT) Register a service map with the container (see {@see ContainerInterface::providers()})
 * @method static void setGlobalContainer(?ContainerInterface $container) Set or unset the global container
 * @method static ContainerInterface singleton(class-string $id, class-string|null $class = null, mixed[] $args = []) Register a shared binding with the container (see {@see ContainerInterface::singleton()})
 * @method static ContainerInterface singletonIf(class-string $id, class-string|null $class = null, mixed[] $args = []) Register a shared binding with the container if it isn't already registered
 * @method static ContainerInterface unbind(class-string $id) Remove a binding from the container
 *
 * @api
 *
 * @extends Facade<ContainerInterface>
 *
 * @generated
 */
final class App extends Facade
{
    /**
     * @inheritDoc
     */
    protected static function getService()
    {
        return [
            ContainerInterface::class => Container::class,
        ];
    }
}
