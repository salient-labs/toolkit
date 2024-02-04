<?php declare(strict_types=1);

namespace Lkrms\Container;

use Dice\Dice;
use Dice\DiceException;
use Lkrms\Concept\FluentInterface;
use Lkrms\Container\Contract\ContainerAwareInterface;
use Lkrms\Container\Contract\ContainerInterface;
use Lkrms\Container\Contract\HasBindings;
use Lkrms\Container\Contract\HasContextualBindings;
use Lkrms\Container\Contract\HasServices;
use Lkrms\Container\Contract\ServiceAwareInterface;
use Lkrms\Container\Contract\ServiceSingletonInterface;
use Lkrms\Container\Contract\SingletonInterface;
use Lkrms\Container\Event\GlobalContainerSetEvent;
use Lkrms\Container\Exception\ContainerNotLocatedException;
use Lkrms\Container\Exception\ContainerServiceNotFoundException;
use Lkrms\Container\Exception\ContainerUnusableArgumentsException;
use Lkrms\Container\Exception\InvalidContainerBindingException;
use Lkrms\Exception\InvalidArgumentException;
use Lkrms\Facade\Event;
use Psr\Container\ContainerInterface as PsrContainerInterface;
use Closure;
use LogicException;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionParameter;

/**
 * A service container with contextual bindings
 */
class Container extends FluentInterface implements ContainerInterface
{
    private const SERVICE_PROVIDER_INTERFACES = [
        ContainerAwareInterface::class,
        ServiceAwareInterface::class,
        SingletonInterface::class,
        ServiceSingletonInterface::class,
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
        if ($this === self::$GlobalContainer) {
            self::setGlobalContainer(null);
        }

        unset($this->Dice);
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
        return self::$GlobalContainer ??= new static();
    }

    /**
     * Get the global container if set
     */
    final public static function maybeGetGlobalContainer(): ?ContainerInterface
    {
        return self::$GlobalContainer;
    }

    /**
     * Get the global container if set, otherwise throw an exception
     *
     * @throws ContainerNotLocatedException if the global container is not set.
     */
    final public static function requireGlobalContainer(): ContainerInterface
    {
        if (self::$GlobalContainer === null) {
            throw new ContainerNotLocatedException();
        }

        return self::$GlobalContainer;
    }

    /**
     * @inheritDoc
     */
    final public static function setGlobalContainer(?ContainerInterface $container): void
    {
        Event::dispatch(new GlobalContainerSetEvent($container));

        self::$GlobalContainer = $container;
    }

    /**
     * @inheritDoc
     */
    final public function get(string $id, array $args = [])
    {
        return $this->_get($id, $id, $args);
    }

    /**
     * @inheritDoc
     */
    final public function getAs(string $id, string $service, array $args = [])
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
    private function _get(string $id, string $service, array $args)
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
    final public function hasInstance(string $id): bool
    {
        return $this->Dice->hasShared($id);
    }

    /**
     * @param array<string,mixed> $rule
     */
    private function checkRule(array $rule): void
    {
        $subs = array_intersect(
            $rule['shareInstances'] ?? [],
            array_keys($rule['substitutions'] ?? [])
        );
        if ($subs) {
            throw new InvalidContainerBindingException(sprintf(
                "Dependencies in 'shareInstances' cannot be substituted: %s",
                implode(', ', $subs),
            ));
        }
    }

    /**
     * @param array<string,mixed> $rule
     */
    private function addRule(string $id, array $rule): void
    {
        $_dice = $this->Dice->addRule($id, $rule);
        $this->checkRule($_dice->getRule($id));
        $this->Dice = $_dice;
    }

    /**
     * @inheritDoc
     */
    final public function inContextOf(string $id)
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
        $defaultRule = [];
        foreach ($subs as $key => $value) {
            if (is_string($value)) {
                if (strcasecmp($this->Dice->getRule($key)['instanceOf'] ?? '', $value)) {
                    $this->addRule($key, ['instanceOf' => $value]);
                }
            } elseif (is_object($value)) {
                if (!$this->Dice->hasShared($key) || $this->get($key) !== $value) {
                    $this->Dice = $this->Dice->addShared($key, $value);
                }
            } elseif (($this->Dice->getDefaultRule()['substitutions'][ltrim($key, '\\')] ?? null) !== $value) {
                // If this substitution can't be converted to a rule, copy it to
                // the default rule and force Dice to use it for the given
                // identifier
                $defaultRule['substitutions'][$key] = $value;
                $this->Dice = $this->Dice->removeRule($key);
            }
        }
        if (!empty($defaultRule)) {
            /**
             * @todo Patch Dice to apply substitutions in `create()`, not just
             * when resolving dependencies
             */
            $this->addRule('*', $defaultRule);
        }
    }

    /**
     * @template TService of object
     * @template T of TService
     *
     * @param class-string<TService> $id
     * @param class-string<T>|null $class
     * @param mixed[] $args
     * @param class-string[] $shared
     * @param array<string,mixed> $rule
     * @return $this
     */
    private function _bind(
        string $id,
        ?string $class,
        array $args,
        array $shared,
        array $rule = []
    ) {
        if ($class !== null) {
            $rule['instanceOf'] = $class;
        }
        if ($args) {
            $rule['constructParams'] = $args;
        }
        if ($shared) {
            $rule['shareInstances'] = $shared;
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
        array $args = [],
        array $shared = []
    ) {
        return $this->_bind($id, $class, $args, $shared);
    }

    /**
     * @inheritDoc
     */
    final public function bindIf(
        string $id,
        ?string $class = null,
        array $args = [],
        array $shared = []
    ) {
        if ($this->has($id)) {
            return $this;
        }

        return $this->_bind($id, $class, $args, $shared);
    }

    /**
     * @inheritDoc
     */
    final public function singleton(
        string $id,
        ?string $class = null,
        array $args = [],
        array $shared = []
    ) {
        return $this->_bind($id, $class, $args, $shared, ['shared' => true]);
    }

    /**
     * @inheritDoc
     */
    final public function singletonIf(
        string $id,
        ?string $class = null,
        array $args = [],
        array $shared = []
    ) {
        if ($this->has($id)) {
            return $this;
        }

        return $this->_bind($id, $class, $args, $shared, ['shared' => true]);
    }

    /**
     * @inheritDoc
     */
    final public function provider(
        string $id,
        ?array $services = null,
        array $exceptServices = [],
        int $lifetime = ServiceLifetime::INHERIT
    ) {
        $this->applyService($id, $services, $exceptServices, $lifetime);
        $this->Providers[$id] = true;
        return $this;
    }

    /**
     * @param class-string $id
     * @param class-string[]|null $services
     * @param class-string[] $exceptServices
     * @param int-mask-of<ServiceLifetime::*> $lifetime
     */
    private function applyService(
        string $id,
        ?array $services = null,
        array $exceptServices = [],
        int $lifetime = ServiceLifetime::INHERIT
    ): void {
        if ($lifetime & ServiceLifetime::INHERIT) {
            $lifetime = 0;
            if (is_a($id, SingletonInterface::class, true)) {
                $lifetime |= ServiceLifetime::SINGLETON;
            }
            if (is_a($id, ServiceSingletonInterface::class, true)) {
                $lifetime |= ServiceLifetime::SERVICE_SINGLETON;
            }
        }

        $rule = [];
        if ($lifetime & ServiceLifetime::SINGLETON) {
            $rule['shared'] = true;

            // If `SINGLETON` and `SERVICE_SINGLETON` are both set, keep service
            // instances separate
            if ($lifetime & ServiceLifetime::SERVICE_SINGLETON) {
                $rule['inherit'] = false;
            }
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
                throw new InvalidContainerBindingException(sprintf(
                    '%s does not implement: %s',
                    $id,
                    implode(', ', array_diff($services, $bind)),
                ));
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
        if ($lifetime & ServiceLifetime::SERVICE_SINGLETON) {
            $rule['shared'] = true;
        }
        foreach ($bind as $service) {
            $this->addRule($service, $rule);
        }
    }

    /**
     * @inheritDoc
     */
    final public function addContextualBinding(
        string $class,
        string $dependency,
        $value
    ) {
        if ($dependency === '') {
            throw new InvalidArgumentException('Argument #2 ($dependency) must be a non-empty string');
        }

        $rule = $this->Dice->hasRule($class)
            ? $this->Dice->getRule($class)
            : [];

        if (is_callable($value)) {
            $value = [Dice::INSTANCE => fn() => $value($this)];
        }

        if (
            $dependency[0] === '$' && (
                !($type = (new ReflectionParameter([$class, '__construct'], substr($dependency, 1)))->getType()) ||
                !$type instanceof ReflectionNamedType ||
                $type->isBuiltin()
            )
        ) {
            $rule['constructParams'][] = $value;
        } else {
            $rule['substitutions'][$dependency] = $value;
        }

        $this->addRule($class, $rule);

        return $this;
    }

    /**
     * @inheritDoc
     */
    final public function instance(string $id, $instance)
    {
        $this->Dice = $this->Dice->addShared($id, $instance);

        return $this;
    }

    /**
     * @inheritDoc
     */
    final public function instanceIf(string $id, $instance)
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
    ) {
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
    final public function unbind(string $id)
    {
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
