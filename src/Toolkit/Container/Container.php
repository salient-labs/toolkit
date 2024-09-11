<?php declare(strict_types=1);

namespace Salient\Container;

use Dice\Dice;
use Dice\DiceException;
use Psr\Container\ContainerInterface as PsrContainerInterface;
use Psr\Log\LoggerInterface;
use Salient\Cache\CacheStore;
use Salient\Console\ConsoleLogger;
use Salient\Console\ConsoleWriter;
use Salient\Container\Event\BeforeGlobalContainerSetEvent;
use Salient\Container\Exception\ArgumentsNotUsedException;
use Salient\Container\Exception\InvalidServiceException;
use Salient\Container\Exception\ServiceNotFoundException;
use Salient\Contract\Cache\CacheInterface;
use Salient\Contract\Console\ConsoleWriterInterface;
use Salient\Contract\Container\ContainerAwareInterface;
use Salient\Contract\Container\ContainerInterface;
use Salient\Contract\Container\HasBindings;
use Salient\Contract\Container\HasContextualBindings;
use Salient\Contract\Container\HasServices;
use Salient\Contract\Container\ServiceAwareInterface;
use Salient\Contract\Container\ServiceLifetime;
use Salient\Contract\Container\SingletonInterface;
use Salient\Contract\Core\FacadeAwareInterface;
use Salient\Contract\Core\FacadeInterface;
use Salient\Contract\Sync\SyncStoreInterface;
use Salient\Core\Concern\HasChainableMethods;
use Salient\Core\Concern\UnloadsFacades;
use Salient\Core\Facade\Event;
use Salient\Sync\SyncStore;
use Closure;
use InvalidArgumentException;
use LogicException;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionParameter;

/**
 * A service container with contextual bindings
 *
 * @implements FacadeAwareInterface<FacadeInterface<self>>
 */
class Container implements ContainerInterface, FacadeAwareInterface
{
    /** @use UnloadsFacades<FacadeInterface<self>> */
    use UnloadsFacades;
    use HasChainableMethods;

    private const SERVICE_PROVIDER_INTERFACES = [
        ContainerAwareInterface::class,
        ServiceAwareInterface::class,
        SingletonInterface::class,
        HasServices::class,
        HasBindings::class,
        HasContextualBindings::class,
    ];

    private const DEFAULT_SERVICES = [
        CacheInterface::class => [CacheStore::class, ServiceLifetime::SINGLETON],
        ConsoleWriterInterface::class => [ConsoleWriter::class, ServiceLifetime::SINGLETON],
        LoggerInterface::class => [ConsoleLogger::class, ServiceLifetime::INHERIT],
        SyncStoreInterface::class => [SyncStore::class, ServiceLifetime::SINGLETON],
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
    final public static function hasGlobalContainer(): bool
    {
        return self::$GlobalContainer !== null;
    }

    /**
     * @inheritDoc
     */
    final public static function getGlobalContainer(): ContainerInterface
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
    final public static function setGlobalContainer(?ContainerInterface $container): void
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
    final public function get(string $id, array $args = []): object
    {
        return $this->_get($id, $id, $args);
    }

    /**
     * @inheritDoc
     */
    final public function getAs(string $id, string $service, array $args = []): object
    {
        return $this->_get($id, $service, $args);
    }

    /**
     * @template T
     * @template TService
     *
     * @param class-string<T> $id
     * @param class-string<TService> $service
     * @param mixed[] $args
     * @return T&TService&object
     */
    private function _get(string $id, string $service, array $args): object
    {
        $hasInstance = $this->Dice->hasShared($id);
        if ($hasInstance && $args) {
            throw new ArgumentsNotUsedException(sprintf(
                'Cannot apply arguments to shared instance: %s',
                $id,
            ));
        }

        if ($hasInstance) {
            $instance = $this->Dice->create($id);

            if ($instance instanceof ServiceAwareInterface) {
                $instance->setService($service);
            }

            /** @var T&TService&object */
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
                    /** @var T&TService&object */
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
        /** @var array{class-string,ServiceLifetime::*} */
        $defaultService = self::DEFAULT_SERVICES[$id];
        [$class, $lifetime] = $defaultService;
        if (
            $lifetime === ServiceLifetime::SINGLETON || (
                $lifetime === ServiceLifetime::INHERIT
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
    final public function getName(string $id): string
    {
        return $this->Dice->getRule($id)['instanceOf'] ?? $id;
    }

    /**
     * @inheritDoc
     */
    final public function has(string $id): bool
    {
        return $this->Dice->hasRule($id) || $this->Dice->hasShared($id);
    }

    /**
     * @inheritDoc
     */
    final public function hasSingleton(string $id): bool
    {
        return $this->Dice->hasShared($id) || (
            $this->Dice->hasRule($id)
            && ($this->Dice->getRule($id)['shared'] ?? false)
        );
    }

    /**
     * @inheritDoc
     */
    final public function hasInstance(string $id): bool
    {
        return $this->Dice->hasShared($id);
    }

    /**
     * @param array<string,mixed> $rule
     */
    private function addRule(string $id, array $rule): void
    {
        $this->Dice = $this->Dice->addRule($id, $rule);
    }

    /**
     * @return static
     */
    final public function inContextOf(string $id): ContainerInterface
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
     * @param array<class-string,string|object|array<string,mixed>> $subs
     */
    private function applyBindings(array $subs): void
    {
        foreach ($subs as $key => $value) {
            if (is_string($value)) {
                if (strcasecmp($this->Dice->getRule($key)['instanceOf'] ?? '', $value)) {
                    $this->addRule($key, ['instanceOf' => $value]);
                }
                continue;
            }
            if (is_object($value)) {
                if (!$this->Dice->hasShared($key) || $this->get($key) !== $value) {
                    $this->Dice = $this->Dice->addShared($key, $value);
                }
                continue;
            }
            $rule = $this->Dice->getDefaultRule();
            // If this substitution can't be converted to a standalone rule,
            // apply it via the default rule
            if (($rule['substitutions'][ltrim($key, '\\')] ?? null) !== $value) {
                $this->Dice = $this->Dice->addSubstitution($key, $value);
            }
        }
    }

    /**
     * @template TService
     * @template T of TService
     *
     * @param class-string<TService> $id
     * @param class-string<T>|null $class
     * @param mixed[] $args
     * @param array<string,mixed> $rule
     * @return $this
     */
    private function _bind(
        string $id,
        ?string $class,
        array $args,
        array $rule = []
    ): ContainerInterface {
        if ($class !== null) {
            $rule['instanceOf'] = $class;
        }

        if ($args) {
            $rule['constructParams'] = $args;
        }

        $this->addRule($id, $rule);

        return $this;
    }

    /**
     * @return $this
     */
    final public function bind(
        string $id,
        ?string $class = null,
        array $args = []
    ): ContainerInterface {
        return $this->_bind($id, $class, $args);
    }

    /**
     * @return $this
     */
    final public function bindIf(
        string $id,
        ?string $class = null,
        array $args = []
    ): ContainerInterface {
        if ($this->has($id)) {
            return $this;
        }

        return $this->_bind($id, $class, $args);
    }

    /**
     * @return $this
     */
    final public function singleton(
        string $id,
        ?string $class = null,
        array $args = []
    ): ContainerInterface {
        return $this->_bind($id, $class, $args, ['shared' => true]);
    }

    /**
     * @return $this
     */
    final public function singletonIf(
        string $id,
        ?string $class = null,
        array $args = []
    ): ContainerInterface {
        if ($this->has($id)) {
            return $this;
        }

        return $this->_bind($id, $class, $args, ['shared' => true]);
    }

    /**
     * @inheritDoc
     */
    final public function hasProvider(string $id): bool
    {
        return isset($this->Providers[$id]);
    }

    /**
     * @return $this
     */
    final public function provider(
        string $id,
        ?array $services = null,
        array $exceptServices = [],
        int $lifetime = ServiceLifetime::INHERIT
    ): ContainerInterface {
        $this->applyService($id, $services, $exceptServices, $lifetime);
        $this->Providers[$id] = true;
        return $this;
    }

    /**
     * @param class-string $id
     * @param class-string[]|null $services
     * @param class-string[] $exceptServices
     * @param ServiceLifetime::* $lifetime
     */
    private function applyService(
        string $id,
        ?array $services = null,
        array $exceptServices = [],
        int $lifetime = ServiceLifetime::INHERIT
    ): void {
        if ($lifetime === ServiceLifetime::INHERIT) {
            $lifetime = is_a($id, SingletonInterface::class, true)
                ? ServiceLifetime::SINGLETON
                : ServiceLifetime::TRANSIENT;
        }

        $rule = [];
        if ($lifetime === ServiceLifetime::SINGLETON) {
            $rule['shared'] = true;
        }

        if (
            is_a($id, HasContextualBindings::class, true)
            && ($bindings = $id::getContextualBindings())
        ) {
            $rule['substitutions'] = $bindings;
        }

        if ($rule) {
            $this->addRule($id, $rule);
        }

        if (is_a($id, HasBindings::class, true)) {
            $bindings = $id::getBindings();
            foreach ($bindings as $service => $class) {
                $this->bind($service, $class);
            }

            $singletons = $id::getSingletons();
            foreach ($singletons as $service => $class) {
                if (is_int($service)) {
                    $service = $class;
                }
                $this->singleton($service, $class);
            }
        }

        if (is_a($id, HasServices::class, true)) {
            $bind = $id::getServices();
        } else {
            $bind = array_diff(
                (new ReflectionClass($id))->getInterfaceNames(),
                self::SERVICE_PROVIDER_INTERFACES,
            );
        }

        if ($services !== null) {
            $services = array_unique($services);
            $bind = array_intersect($bind, $services);
            if (count($bind) < count($services)) {
                // @codeCoverageIgnoreStart
                throw new InvalidServiceException(sprintf(
                    '%s does not implement: %s',
                    $id,
                    implode(', ', array_diff($services, $bind)),
                ));
                // @codeCoverageIgnoreEnd
            }
        }

        if ($exceptServices) {
            $bind = array_diff($bind, $exceptServices);
        }

        if (!$bind) {
            return;
        }

        $rule = [
            'instanceOf' => $id
        ];
        foreach ($bind as $service) {
            $this->addRule($service, $rule);
        }
    }

    /**
     * @return $this
     */
    final public function addContextualBinding($context, string $dependency, $value): ContainerInterface
    {
        if (is_array($context)) {
            foreach ($context as $_context) {
                $this->addContextualBinding($_context, $dependency, $value);
            }
            return $this;
        }

        if ($dependency === '') {
            // @codeCoverageIgnoreStart
            throw new InvalidArgumentException('Argument #2 ($dependency) must be a non-empty string');
            // @codeCoverageIgnoreEnd
        }

        $rule = $this->Dice->hasRule($context)
            ? $this->Dice->getRule($context)
            : [];

        if (is_callable($value)) {
            $value = [Dice::INSTANCE => fn() => $value($this)];
        }

        if (
            $dependency[0] === '$' && (
                !($type = (new ReflectionParameter([$context, '__construct'], substr($dependency, 1)))->getType())
                || !$type instanceof ReflectionNamedType
                || $type->isBuiltin()
            )
        ) {
            $rule['constructParams'][] = $value;
        } else {
            $rule['substitutions'][$dependency] = $value;
        }

        $this->addRule($context, $rule);

        return $this;
    }

    /**
     * @return $this
     */
    final public function instance(string $id, $instance): ContainerInterface
    {
        $this->Dice = $this->Dice->addShared($id, $instance);

        return $this;
    }

    /**
     * @return $this
     */
    final public function providers(
        array $serviceMap,
        int $lifetime = ServiceLifetime::INHERIT
    ): ContainerInterface {
        $idMap = [];
        foreach ($serviceMap as $id => $class) {
            if (!class_exists($class)) {
                throw new LogicException(sprintf(
                    'Not a class: %s',
                    $class,
                ));
            }
            if (!is_a($class, $id, true)) {
                throw new LogicException(sprintf(
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
            $this->provider($class, $services, [], $lifetime);
        }

        return $this;
    }

    /**
     * @inheritDoc
     */
    final public function getProviders(): array
    {
        return array_keys($this->Providers);
    }

    /**
     * @return $this
     */
    final public function unbind(string $id): ContainerInterface
    {
        $this->Dice = $this->Dice->removeRule($id);

        return $this;
    }

    /**
     * @return $this
     */
    final public function removeInstance(string $id): ContainerInterface
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
