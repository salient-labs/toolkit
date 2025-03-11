<?php declare(strict_types=1);

namespace Salient\Container;

use Dice\Dice;
use Dice\DiceException;
use Psr\Container\ContainerInterface as PsrContainerInterface;
use Psr\Log\LoggerInterface;
use Salient\Cache\CacheStore;
use Salient\Console\Console;
use Salient\Console\ConsoleLogger;
use Salient\Container\Event\BeforeGlobalContainerSetEvent;
use Salient\Container\Exception\InvalidServiceException;
use Salient\Container\Exception\ServiceNotFoundException;
use Salient\Container\Exception\UnusedArgumentsException;
use Salient\Contract\Cache\CacheInterface;
use Salient\Contract\Console\ConsoleInterface;
use Salient\Contract\Container\ContainerAwareInterface;
use Salient\Contract\Container\ContainerInterface;
use Salient\Contract\Container\HasBindings;
use Salient\Contract\Container\HasContextualBindings;
use Salient\Contract\Container\HasServices;
use Salient\Contract\Container\ServiceAwareInterface;
use Salient\Contract\Container\SingletonInterface;
use Salient\Contract\Core\Event\EventDispatcherInterface;
use Salient\Contract\Core\Facade\FacadeAwareInterface;
use Salient\Contract\Sync\SyncStoreInterface;
use Salient\Core\Concern\ChainableTrait;
use Salient\Core\Concern\FacadeAwareTrait;
use Salient\Core\Event\EventDispatcher;
use Salient\Core\Facade\Event;
use Salient\Sync\SyncStore;
use Closure;
use InvalidArgumentException;
use ReflectionClass;

/**
 * @api
 *
 * @implements FacadeAwareInterface<ContainerInterface>
 */
class Container implements ContainerInterface, FacadeAwareInterface
{
    /** @use FacadeAwareTrait<ContainerInterface> */
    use FacadeAwareTrait;
    use ChainableTrait;

    private const SERVICE_PROVIDER_INTERFACES = [
        ContainerAwareInterface::class,
        ServiceAwareInterface::class,
        SingletonInterface::class,
        HasServices::class,
        HasBindings::class,
        HasContextualBindings::class,
    ];

    private const DEFAULT_SERVICES = [
        CacheInterface::class => [CacheStore::class, self::LIFETIME_SINGLETON],
        EventDispatcherInterface::class => [EventDispatcher::class, self::LIFETIME_SINGLETON],
        ConsoleInterface::class => [Console::class, self::LIFETIME_SINGLETON],
        LoggerInterface::class => [ConsoleLogger::class, self::LIFETIME_INHERIT],
        SyncStoreInterface::class => [SyncStore::class, self::LIFETIME_SINGLETON],
    ];

    private static ?ContainerInterface $GlobalContainer = null;
    private Dice $Dice;
    /** @var array<class-string,true> */
    private array $Providers = [];
    /** @var array<class-string,class-string> */
    private array $GetAsServiceMap = [];

    /**
     * @inheritDoc
     */
    public function __construct()
    {
        $this->Dice = new Dice();
        $this->bindContainer();
    }

    /**
     * @inheritDoc
     */
    public function unload(): void
    {
        if (self::$GlobalContainer === $this) {
            self::setGlobalContainer(null);
        }

        $this->unloadFacades();

        $this->Dice = new Dice();
        $this->bindContainer();
    }

    private function bindContainer(): void
    {
        $class = new ReflectionClass(static::class);

        // Bind interfaces that extend Psr\Container\ContainerInterface
        /** @var class-string $name */
        foreach ($class->getInterfaces() as $name => $interface) {
            if ($interface->implementsInterface(PsrContainerInterface::class)) {
                $this->instance($name, $this);
            }
        }

        // Also bind classes between self and static
        do {
            $this->instance($class->getName(), $this);
        } while (
            $class->isSubclassOf(self::class)
            && ($class = $class->getParentClass())
        );

        $this->Dice = $this->Dice->addCallback(
            '*',
            Closure::fromCallable([$this, 'callback']),
            __METHOD__,
        );
    }

    /**
     * @param class-string $name
     */
    private function callback(object $instance, string $name): object
    {
        if ($instance instanceof ContainerAwareInterface) {
            $instance->setContainer($this);
        }

        if ($instance instanceof ServiceAwareInterface) {
            $instance->setService($this->GetAsServiceMap[$name] ?? $name);
        }

        return $instance;
    }

    /**
     * @inheritDoc
     */
    public static function hasGlobalContainer(): bool
    {
        return self::$GlobalContainer !== null;
    }

    /**
     * @inheritDoc
     */
    public static function getGlobalContainer(): ContainerInterface
    {
        if (self::$GlobalContainer === null) {
            $container = new static();
            self::setGlobalContainer($container);
            return $container;
        }

        return self::$GlobalContainer;
    }

    /**
     * @inheritDoc
     */
    public static function setGlobalContainer(?ContainerInterface $container): void
    {
        if (self::$GlobalContainer === $container) {
            return;
        }

        Event::dispatch(new BeforeGlobalContainerSetEvent($container));

        self::$GlobalContainer = $container;
    }

    /**
     * @inheritDoc
     */
    public function get(string $id, array $args = []): object
    {
        return $this->_get($id, $id, $args);
    }

    /**
     * @inheritDoc
     */
    public function getAs(string $id, string $service, array $args = []): object
    {
        if (!is_a($id, $service, true)) {
            throw new InvalidArgumentException(sprintf(
                '%s does not inherit %s',
                $id,
                $service,
            ));
        }
        return $this->_get($id, $service, $args);
    }

    /**
     * @template TService
     * @template T of TService
     *
     * @param class-string<T> $id
     * @param class-string<TService> $service
     * @param mixed[] $args
     * @return T&object
     */
    private function _get(string $id, string $service, array $args): object
    {
        $hasInstance = $this->Dice->hasShared($id);
        if ($hasInstance && $args) {
            throw new UnusedArgumentsException(sprintf(
                'Cannot apply arguments to shared instance: %s',
                $id,
            ));
        }

        if ($hasInstance) {
            $instance = $this->Dice->create($id);

            if ($instance instanceof ServiceAwareInterface) {
                $instance->setService($service);
            }

            /** @var T&object */
            return $instance;
        }

        if ($service !== $id) {
            $this->GetAsServiceMap[$id] = $service;
        }

        if (isset(self::DEFAULT_SERVICES[$id]) && !$this->Dice->hasRule($id)) {
            $this->bindDefaultService($id);
        }

        try {
            do {
                try {
                    /** @var T&object */
                    return $this->Dice->create($id, $args);
                } catch (DiceException $ex) {
                    /** @var class-string|null */
                    $failed = $ex->getClass();
                    if (
                        $failed !== null
                        && isset(self::DEFAULT_SERVICES[$failed])
                        && !$this->has($failed)
                    ) {
                        $this->bindDefaultService($failed);
                        continue;
                    }
                    throw new ServiceNotFoundException($ex->getMessage(), $ex);
                }
            } while (true);
        } finally {
            if ($service !== $id) {
                unset($this->GetAsServiceMap[$id]);
            }
        }
    }

    /**
     * @param class-string $id
     */
    private function bindDefaultService(string $id): void
    {
        $defaultService = self::DEFAULT_SERVICES[$id];
        /** @var class-string */
        $class = $defaultService[0];
        /** @var self::LIFETIME_* */
        $lifetime = $defaultService[1];
        if (
            $lifetime === self::LIFETIME_SINGLETON || (
                $lifetime === self::LIFETIME_INHERIT
                && is_a($class, SingletonInterface::class, true)
            )
        ) {
            $this->singleton($id, $class);
        } else {
            $this->bind($id, $class);
        }
    }

    /**
     * @inheritDoc
     */
    public function getClass(string $id): string
    {
        return $this->Dice->hasShared($id)
            ? get_class($this->Dice->create($id))
            : $this->Dice->getRule($id)['instanceOf'] ?? $id;
    }

    /**
     * @inheritDoc
     */
    public function has(string $id): bool
    {
        return $this->Dice->hasRule($id) || $this->Dice->hasShared($id);
    }

    /**
     * @inheritDoc
     */
    public function hasSingleton(string $id): bool
    {
        return $this->Dice->hasShared($id) || (
            $this->Dice->hasRule($id)
            && ($this->Dice->getRule($id)['shared'] ?? false)
        );
    }

    /**
     * @inheritDoc
     */
    public function hasInstance(string $id): bool
    {
        return $this->Dice->hasShared($id);
    }

    /**
     * @param array<string,mixed> $rule
     */
    private function addRule(string $id, array $rule, bool $remove = false): void
    {
        if ($remove) {
            $this->Dice = $this->Dice->removeRule($id);
        }

        $this->Dice = $this->Dice->addRule($id, $rule);
    }

    /**
     * @inheritDoc
     */
    public function inContextOf(string $id): ContainerInterface
    {
        $clone = clone $this;

        // If not already registered, register $id as a service provider without
        // binding services that may be bound to other providers
        if (!isset($this->Providers[$id])) {
            $clone->applyService($id, []);
            $clone->Providers[$id] = true;

            // If nothing changed, skip `applyService()` in future
            if (!$this->compareBindingsWith($clone)) {
                $this->Providers[$id] = true;
            }
        }

        if (!$clone->Dice->hasRule($id)) {
            return $this;
        }

        $subs = $clone->Dice->getRule($id)['substitutions'] ?? null;
        if (!$subs) {
            return $this;
        }

        $clone->applyBindings($subs);

        if (!$this->compareBindingsWith($clone)) {
            return $this;
        }

        $clone->bindContainer();

        return $clone;
    }

    /**
     * @param array<class-string,class-string|object|non-empty-array<Dice::INSTANCE,Closure(): object>> $subs
     */
    private function applyBindings(array $subs): void
    {
        foreach ($subs as $key => $value) {
            if (is_string($value)) {
                if (strcasecmp($this->Dice->getRule($key)['instanceOf'] ?? '', $value)) {
                    $this->addRule($key, ['instanceOf' => $value]);
                }
            } elseif (is_object($value)) {
                if (!$this->Dice->hasShared($key) || $this->get($key) !== $value) {
                    $this->Dice = $this->Dice->addShared($key, $value);
                }
            } else {
                $value = $value[Dice::INSTANCE];
                if (($this->Dice->getRule($key)['callback'] ?? null) !== $value) {
                    $this->addRule($key, ['callback' => $value]);
                }
            }
        }
    }

    /**
     * @template TService
     * @template T of TService
     *
     * @param class-string<TService> $id
     * @param (Closure(ContainerInterface): T&object)|class-string<T>|null $class
     * @param array<string,mixed> $rule
     * @phpstan-return $this
     */
    private function _bind(string $id, $class, array $rule = []): ContainerInterface
    {
        if ($class instanceof Closure) {
            $rule['callback'] = fn() => $class($this);
        } elseif ($class !== null) {
            $rule['instanceOf'] = $class;
        }

        $this->addRule($id, $rule, true);

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function bind(string $id, $class = null): ContainerInterface
    {
        return $this->_bind($id, $class);
    }

    /**
     * @inheritDoc
     */
    public function bindIf(string $id, $class = null): ContainerInterface
    {
        if ($this->has($id)) {
            return $this;
        }

        return $this->_bind($id, $class);
    }

    /**
     * @inheritDoc
     */
    public function singleton(string $id, $class = null): ContainerInterface
    {
        return $this->_bind($id, $class, ['shared' => true]);
    }

    /**
     * @inheritDoc
     */
    public function singletonIf(string $id, $class = null): ContainerInterface
    {
        if ($this->has($id)) {
            return $this;
        }

        return $this->_bind($id, $class, ['shared' => true]);
    }

    /**
     * @inheritDoc
     */
    public function hasProvider(string $provider): bool
    {
        return isset($this->Providers[$provider]);
    }

    /**
     * @inheritDoc
     */
    public function provider(
        string $provider,
        ?array $services = null,
        array $excludeServices = [],
        int $providerLifetime = Container::LIFETIME_INHERIT
    ): ContainerInterface {
        $this->applyService($provider, $services, $excludeServices, $providerLifetime);
        $this->Providers[$provider] = true;
        return $this;
    }

    /**
     * @param class-string $provider
     * @param class-string[]|null $services
     * @param class-string[] $excludeServices
     * @param self::LIFETIME_* $providerLifetime
     */
    private function applyService(
        string $provider,
        ?array $services = null,
        array $excludeServices = [],
        int $providerLifetime = self::LIFETIME_INHERIT
    ): void {
        if ($providerLifetime === self::LIFETIME_INHERIT) {
            $providerLifetime = is_a($provider, SingletonInterface::class, true)
                ? self::LIFETIME_SINGLETON
                : self::LIFETIME_TRANSIENT;
        }

        $rule = [];
        if ($providerLifetime === self::LIFETIME_SINGLETON) {
            $rule['shared'] = true;
        }

        if (
            is_a($provider, HasContextualBindings::class, true)
            && ($bindings = $provider::getContextualBindings($this))
        ) {
            foreach ($bindings as $service => $class) {
                if (is_int($service)) {
                    if (!is_string($class)) {
                        throw new InvalidServiceException(sprintf(
                            'Unmapped services must be of type class-string: %s::getContextualBindings()',
                            $provider,
                        ));
                    }
                    $service = $class;
                }

                if ($class instanceof Closure) {
                    $class = [Dice::INSTANCE => fn() => $class($this)];
                }

                if ($service[0] === '$') {
                    if ($constructor = (new ReflectionClass($provider))->getConstructor()) {
                        $name = substr($service, 1);
                        foreach ($constructor->getParameters() as $param) {
                            if ($param->getName() === $name) {
                                $rule['constructParams'][$name] = is_object($class)
                                    ? [Dice::INSTANCE => fn() => $class]
                                    : $class;
                                break;
                            }
                        }
                    }
                } else {
                    $rule['substitutions'][$service] = $class;
                }
            }
        }

        if ($rule) {
            $this->addRule($provider, $rule);
        }

        if (is_a($provider, HasBindings::class, true)) {
            $bindings = $provider::getBindings($this);
            foreach ($bindings as $service => $class) {
                $this->bind($service, $class);
            }

            $singletons = $provider::getSingletons($this);
            foreach ($singletons as $service => $class) {
                if (is_int($service)) {
                    if (!is_string($class)) {
                        throw new InvalidServiceException(sprintf(
                            'Unmapped services must be of type class-string: %s::getSingletons()',
                            $provider,
                        ));
                    }
                    $service = $class;
                }
                $this->singleton($service, $class);
            }
        }

        if (is_a($provider, HasServices::class, true)) {
            $bind = $provider::getServices();
        } else {
            $bind = array_diff(
                (new ReflectionClass($provider))->getInterfaceNames(),
                self::SERVICE_PROVIDER_INTERFACES,
            );
        }

        if ($services !== null) {
            $services = array_unique($services);
            $bind = array_intersect($bind, $services);
            if (count($bind) < count($services)) {
                throw new InvalidServiceException(sprintf(
                    '%s does not implement: %s',
                    $provider,
                    implode(', ', array_diff($services, $bind)),
                ));
            }
        }

        if ($excludeServices) {
            $bind = array_diff($bind, $excludeServices);
        }

        if (!$bind) {
            return;
        }

        $rule = [
            'instanceOf' => $provider
        ];
        foreach ($bind as $service) {
            $this->addRule($service, $rule);
        }
    }

    /**
     * @inheritDoc
     */
    public function addContextualBinding($context, string $id, $class = null): ContainerInterface
    {
        if (is_array($context)) {
            foreach ($context as $_context) {
                $this->addContextualBinding($_context, $id, $class);
            }
            return $this;
        }

        $rule = $this->Dice->hasRule($context)
            ? $this->Dice->getRule($context)
            : [];

        if ($class instanceof Closure) {
            $class = [Dice::INSTANCE => fn() => $class($this)];
        }

        if ($id[0] === '$') {
            if ($class === null) {
                throw new InvalidArgumentException('$class cannot be null when $id starts with \'$\'');
            }
            $applied = false;
            if ($constructor = (new ReflectionClass($context))->getConstructor()) {
                $name = substr($id, 1);
                foreach ($constructor->getParameters() as $param) {
                    if ($param->getName() === $name) {
                        $rule['constructParams'][$name] = is_object($class)
                            ? [Dice::INSTANCE => fn() => $class]
                            : $class;
                        $applied = true;
                        break;
                    }
                }
            }
            if (!$applied) {
                return $this;
            }
        } else {
            $rule['substitutions'][$id] = $class ?? $id;
        }

        $this->addRule($context, $rule);

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function instance(string $id, object $instance): ContainerInterface
    {
        $this->Dice = $this->Dice->addShared($id, $instance);

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function providers(
        array $providers,
        int $providerLifetime = Container::LIFETIME_INHERIT
    ): ContainerInterface {
        $idMap = [];
        foreach ($providers as $id => $class) {
            if (is_int($id)) {
                $id = $class;
            }
            if (!class_exists($class)) {
                throw new InvalidArgumentException(sprintf(
                    'Not a class: %s',
                    $class,
                ));
            }
            if (!is_a($class, $id, true)) {
                throw new InvalidArgumentException(sprintf(
                    '%s does not inherit %s',
                    $class,
                    $id,
                ));
            }
            if (is_a($id, $class, true)) {
                // Don't add classes mapped to themselves to their service list
                $idMap[$class] ??= [];
                continue;
            }
            $idMap[$class][] = $id;
        }

        foreach ($idMap as $class => $services) {
            $this->provider($class, $services, [], $providerLifetime);
        }

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function getProviders(): array
    {
        return array_keys($this->Providers);
    }

    /**
     * @inheritDoc
     */
    public function removeInstance(string $id): ContainerInterface
    {
        if (!$this->Dice->hasShared($id)) {
            return $this;
        }

        if ($this->Dice->hasRule($id)) {
            // Reapplying the rule removes the instance
            $this->Dice = $this->Dice->addRule($id, $this->Dice->getRule($id));
            return $this;
        }

        $this->Dice = $this->Dice->removeRule($id);
        return $this;
    }

    /**
     * 0 if another container has the same bindings, otherwise 1
     *
     * @param static $container
     */
    private function compareBindingsWith($container): int
    {
        return $this->Dice === $container->Dice ? 0 : 1;
    }
}
