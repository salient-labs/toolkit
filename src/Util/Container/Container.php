<?php declare(strict_types=1);

namespace Lkrms\Container;

use Dice\Dice;
use Dice\DiceException;
use Lkrms\Container\Contract\ContainerAwareInterface;
use Lkrms\Container\Contract\HasBindings;
use Lkrms\Container\Contract\HasContextualBindings;
use Lkrms\Container\Contract\HasServices;
use Lkrms\Container\Contract\ServiceAwareInterface;
use Lkrms\Container\Contract\SingletonInterface;
use Lkrms\Container\Event\BeforeGlobalContainerSetEvent;
use Lkrms\Container\Exception\ContainerNotFoundException;
use Lkrms\Container\Exception\ContainerServiceNotFoundException;
use Lkrms\Container\Exception\ContainerUnusableArgumentsException;
use Lkrms\Container\Exception\InvalidContainerBindingException;
use Psr\Container\ContainerInterface as PsrContainerInterface;
use Salient\Core\Concern\HasChainableMethods;
use Salient\Core\Concern\UnloadsFacades;
use Salient\Core\Contract\FacadeAwareInterface;
use Salient\Core\Contract\FacadeInterface;
use Salient\Core\Exception\InvalidArgumentException;
use Salient\Core\Facade\Event;
use Closure;
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

    private static ?ContainerInterface $GlobalContainer = null;

    private Dice $Dice;

    /**
     * @var array<class-string,true>
     */
    private array $Providers = [];

    /**
     * @var array<class-string,class-string>
     */
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
        foreach ($class->getInterfaces() as $name => $interface) {
            if ($interface->implementsInterface(PsrContainerInterface::class)) {
                $this->instance($name, $this);
            }
        }

        // Also bind classes between self and static
        do {
            $this->instance($class->getName(), $this);
        } while (
            $class->isSubclassOf(self::class) &&
            ($class = $class->getParentClass())
        );

        $this->Dice = $this->Dice->addCallback(
            '*',
            Closure::fromCallable([$this, 'callback']),
            __METHOD__,
        );
    }

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
            self::setGlobalContainer(new static());
        }

        return self::$GlobalContainer;
    }

    /**
     * Get the global container if set
     *
     * @api
     */
    final public static function maybeGetGlobalContainer(): ?ContainerInterface
    {
        return self::$GlobalContainer;
    }

    /**
     * Get the global container if set, otherwise throw an exception
     *
     * @api
     *
     * @throws ContainerNotFoundException if the global container is not set.
     */
    final public static function requireGlobalContainer(): ContainerInterface
    {
        if (self::$GlobalContainer === null) {
            throw new ContainerNotFoundException();
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
     * @template TService of object
     * @template T of TService
     *
     * @param class-string<T> $id
     * @param class-string<TService> $service
     * @param mixed[] $args
     * @return T
     */
    private function _get(string $id, string $service, array $args): object
    {
        $hasInstance = $this->Dice->hasShared($id);
        if ($hasInstance && $args) {
            throw new ContainerUnusableArgumentsException(sprintf(
                'Cannot apply arguments to shared instance: %s',
                $id,
            ));
        }

        if ($hasInstance) {
            $instance = $this->Dice->create($id);

            if ($instance instanceof ServiceAwareInterface) {
                $instance->setService($service);
            }

            return $instance;
        }

        if ($service !== $id) {
            $this->GetAsServiceMap[$id] = $service;
        }

        try {
            return $this->Dice->create($id, $args);
        } catch (DiceException $ex) {
            throw new ContainerServiceNotFoundException($ex->getMessage(), $ex);
        } finally {
            if ($service !== $id) {
                unset($this->GetAsServiceMap[$id]);
            }
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
            $this->Dice->hasRule($id) &&
            ($this->Dice->getRule($id)['shared'] ?? false)
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
     * @inheritDoc
     */
    final public function inContextOf(string $id): self
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
     * @param array<string,string|object|array<string,mixed>> $subs
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
     * @template TService of object
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
    ): self {
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
     * @inheritDoc
     */
    final public function bind(
        string $id,
        ?string $class = null,
        array $args = []
    ): self {
        return $this->_bind($id, $class, $args);
    }

    /**
     * @inheritDoc
     */
    final public function bindIf(
        string $id,
        ?string $class = null,
        array $args = []
    ): self {
        if ($this->has($id)) {
            return $this;
        }

        return $this->_bind($id, $class, $args);
    }

    /**
     * @inheritDoc
     */
    final public function singleton(
        string $id,
        ?string $class = null,
        array $args = []
    ): self {
        return $this->_bind($id, $class, $args, ['shared' => true]);
    }

    /**
     * @inheritDoc
     */
    final public function singletonIf(
        string $id,
        ?string $class = null,
        array $args = []
    ): self {
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
     * @inheritDoc
     */
    final public function provider(
        string $id,
        ?array $services = null,
        array $exceptServices = [],
        int $lifetime = ServiceLifetime::INHERIT
    ): self {
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
            is_a($id, HasContextualBindings::class, true) &&
            ($bindings = $id::getContextualBindings())
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
                throw new InvalidContainerBindingException(sprintf(
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
     * @inheritDoc
     */
    final public function addContextualBinding($context, string $dependency, $value): self
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
                !($type = (new ReflectionParameter([$context, '__construct'], substr($dependency, 1)))->getType()) ||
                !$type instanceof ReflectionNamedType ||
                $type->isBuiltin()
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
     * @inheritDoc
     */
    final public function instance(string $id, $instance): self
    {
        $this->Dice = $this->Dice->addShared($id, $instance);

        return $this;
    }

    /**
     * @inheritDoc
     */
    final public function instanceIf(string $id, $instance): self
    {
        if ($this->has($id)) {
            return $this;
        }

        return $this->instance($id, $instance);
    }

    /**
     * @inheritDoc
     */
    final public function providers(
        array $serviceMap,
        int $lifetime = ServiceLifetime::INHERIT
    ): self {
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
     * @inheritDoc
     */
    final public function unbind(string $id): self
    {
        $this->Dice = $this->Dice->removeRule($id);

        return $this;
    }

    /**
     * @inheritDoc
     */
    final public function unbindInstance(string $id): self
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
