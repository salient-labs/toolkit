<?php declare(strict_types=1);

namespace Salient\Core\Facade;

use Salient\Container\Container;
use Salient\Contract\Container\ContainerInterface;
use Salient\Contract\Container\ServiceLifetime;
use Salient\Contract\Core\Chainable;
use Salient\Core\AbstractFacade;

/**
 * A facade for the global service container
 *
 * @method static ContainerInterface addContextualBinding(class-string[]|class-string $context, class-string|string $dependency, (callable(ContainerInterface): mixed)|class-string|mixed $value) Register a contextual binding with the container (see {@see ContainerInterface::addContextualBinding()})
 * @method static ContainerInterface apply(callable(static): static $callback) Move to the next method in the chain after applying a callback to the object
 * @method static ContainerInterface applyForEach(iterable<mixed,mixed> $items, callable(static, mixed, mixed): static $callback) Move to the next method in the chain after applying a callback to the object for each item in an array or iterator
 * @method static ContainerInterface applyIf((callable(static): bool)|bool $condition, (callable(static): static)|null $then = null, (callable(static): static)|null $else = null) Move to the next method in the chain after applying a conditional callback to the object (see {@see Chainable::applyIf()})
 * @method static ContainerInterface bind(class-string $id, class-string|null $class = null, mixed[] $args = []) Bind a service to the container (see {@see ContainerInterface::bind()})
 * @method static ContainerInterface bindIf(class-string $id, class-string|null $class = null, mixed[] $args = []) Bind a service to the container if it isn't already bound
 * @method static object get(class-string $id, mixed[] $args = []) Resolve a service from the container (see {@see ContainerInterface::get()})
 * @method static object getAs(class-string $id, class-string $service, mixed[] $args = []) Resolve a partially-resolved service from the container (see {@see ContainerInterface::getAs()})
 * @method static ContainerInterface getGlobalContainer() Get the global container, creating it if necessary
 * @method static class-string getName(class-string $id) Resolve a service to a concrete class name
 * @method static array<class-string> getProviders() Get a list of service providers registered with the container
 * @method static bool has(class-string $id) Check if a service is bound to the container (see {@see ContainerInterface::has()})
 * @method static bool hasGlobalContainer() Check if the global container is set
 * @method static bool hasInstance(class-string $id) Check if a service resolves to a shared instance
 * @method static bool hasProvider(class-string $id) Check if a service provider is registered with the container
 * @method static bool hasSingleton(class-string $id) Check if a shared service is bound to the container
 * @method static ContainerInterface inContextOf(class-string $id) Apply the contextual bindings of a service to a copy of the container
 * @method static ContainerInterface instance(class-string $id, object $instance) Bind a shared instance to the container
 * @method static ContainerInterface provider(class-string $id, class-string[]|null $services = null, class-string[] $exceptServices = [], ServiceLifetime::* $lifetime = ServiceLifetime::INHERIT) Register a service provider with the container, optionally specifying which of its services to bind or ignore (see {@see ContainerInterface::provider()})
 * @method static ContainerInterface providers(array<class-string,class-string> $serviceMap, ServiceLifetime::* $lifetime = ServiceLifetime::INHERIT) Register a service map with the container (see {@see ContainerInterface::providers()})
 * @method static ContainerInterface removeInstance(class-string $id) Remove a shared instance from the container
 * @method static void setGlobalContainer(ContainerInterface|null $container) Set or unset the global container
 * @method static ContainerInterface singleton(class-string $id, class-string|null $class = null, mixed[] $args = []) Bind a shared service to the container (see {@see ContainerInterface::singleton()})
 * @method static ContainerInterface singletonIf(class-string $id, class-string|null $class = null, mixed[] $args = []) Bind a shared service to the container if it isn't already bound
 * @method static ContainerInterface unbind(class-string $id) Remove a binding from the container
 *
 * @api
 *
 * @extends AbstractFacade<ContainerInterface>
 *
 * @generated
 */
final class App extends AbstractFacade
{
    /**
     * @internal
     */
    protected static function getService()
    {
        return [
            ContainerInterface::class => Container::class,
        ];
    }
}
