<?php declare(strict_types=1);

namespace Lkrms\Container;

use Dice\Dice;
use Lkrms\Concept\FluentInterface;
use Lkrms\Contract\IContainer;
use Lkrms\Contract\IService;
use Lkrms\Contract\IServiceShared;
use Lkrms\Contract\IServiceSingleton;
use Lkrms\Contract\ReceivesContainer;
use Lkrms\Contract\ReceivesService;
use RuntimeException;
use UnexpectedValueException;

/**
 * A service container with support for contextual bindings
 *
 * Typically accessed via the {@see \Lkrms\Facade\DI} facade.
 *
 */
class Container extends FluentInterface implements IContainer
{
    /**
     * @var IContainer|null
     */
    private static $GlobalContainer;

    /**
     * @var Dice|null
     */
    private $Dice;

    /**
     * @var string|null
     */
    private $Context;

    /**
     * Whenever `inContextOf($id)` clones the container, `$id` is pushed onto
     * the end
     *
     * @var string[]
     */
    private $ContextStack = [];

    /**
     * When an `IService` class is bound to the container,
     * `$this->Services[$name] = true` is applied
     *
     * @var array<string,true>
     */
    private $Services = [];

    public function __construct()
    {
        $this->Dice = new Dice();
        $this->load();
    }

    private function load(): void
    {
        $this->Dice = $this->Dice->addShared(\Psr\Container\ContainerInterface::class, $this)
                                 ->addShared(IContainer::class, $this);
        $class = static::class;
        do {
            $this->Dice = $this->Dice->addShared($class, $this);
        } while (self::class != $class && ($class = get_parent_class($class)));
        $this->Dice = $this->Dice->addCallback(
            '*',
            function (object $instance, string $name): object {
                return $this->callback($instance, $name);
            }
        );
    }

    private function callback(object $instance, string $name): object
    {
        if ($instance instanceof ReceivesContainer) {
            $instance = $instance->setContainer($this);
        }
        if ($instance instanceof ReceivesService) {
            $instance = $instance->setService($name);
        }

        return $instance;
    }

    final public static function hasGlobalContainer(): bool
    {
        return !is_null(self::$GlobalContainer);
    }

    final public static function getGlobalContainer(): IContainer
    {
        if (!is_null(self::$GlobalContainer)) {
            return self::$GlobalContainer;
        }

        return self::$GlobalContainer = new static(...func_get_args());
    }

    /**
     * Similar to getGlobalContainer(), but return null if no global container
     * has been loaded
     */
    final public static function maybeGetGlobalContainer(): ?IContainer
    {
        return self::$GlobalContainer;
    }

    /**
     * Similar to getGlobalContainer(), but throw an exception if no global
     * container has been loaded
     */
    final public static function requireGlobalContainer(): IContainer
    {
        if (is_null(self::$GlobalContainer)) {
            throw new RuntimeException('No service container located');
        }

        return self::$GlobalContainer;
    }

    final public static function setGlobalContainer(?IContainer $container): ?IContainer
    {
        return self::$GlobalContainer = $container;
    }

    final public function get(string $id, ...$params)
    {
        return $this->Dice->create($id, $params);
    }

    final public function getAs(string $id, string $serviceId, ...$params)
    {
        if ($this->Dice->hasShared($id)) {
            $instance = $this->Dice->create($id);
            if ($instance instanceof ReceivesService) {
                return $instance->setService($serviceId);
            }

            return $instance;
        }

        return $this->Dice->addCallback(
            '*',
            function (object $instance, string $name, bool &$continue) use ($id, $serviceId): object {
                if (!strcasecmp(get_class($instance), $id)) {
                    $continue = false;

                    return $this->callback($instance, $serviceId);
                }

                return $this->callback($instance, $name);
            },
            null,
            true
        )->create($id, $params);
    }

    final public function getName(string $id): string
    {
        return $this->Dice->getRule($id)['instanceOf'] ?? $id;
    }

    final public function has(string $id): bool
    {
        return class_exists($this->getName($id));
    }

    private function checkRule(array $rule): void
    {
        if (!empty(
            $subs = array_intersect(
                $rule['shareInstances'] ?? [],
                array_keys($rule['substitutions'] ?? [])
            )
        )) {
            throw new UnexpectedValueException("Dependencies in 'shareInstances' cannot be substituted: " . implode(', ', $subs));
        }
    }

    private function addRule(string $id, array $rule): void
    {
        $_dice = $this->Dice->addRule($id, $rule);
        $this->checkRule($_dice->getRule($id));
        $this->Dice = $_dice;
    }

    final public function inContextOf(string $id): Container
    {
        $clone = clone $this;

        // If $id implements IService and hasn't been bound to the container
        // yet, add bindings for everything except its services, which may
        // resolve to another provider
        if (is_subclass_of($id, IService::class) && !($clone->Services[$id] ?? null)) {
            $clone->applyService($id, []);
            $clone->Services[$id] = true;

            // If nothing changed, skip `applyService()` in future by setting
            // `$this->Services[$id]`
            if ($clone->Dice === $this->Dice) {
                $this->Services[$id] = true;
            }
        }

        if (!$clone->Dice->hasRule($id) ||
                empty($subs = $clone->Dice->getRule($id)['substitutions'] ?? null)) {
            return $this;
        }

        $clone->applyBindings($subs);

        if ($clone->Dice === $this->Dice) {
            return $this;
        }

        $clone->Context = $clone->ContextStack[] = $id;
        $clone->load();

        return $clone;
    }

    private function applyBindings(array $subs): void
    {
        $defaultRule = [];
        foreach ($subs as $key => $value) {
            if (is_string($value)) {
                if (strcasecmp($this->Dice->getRule($key)['instanceOf'] ?? '', $value)) {
                    $this->addRule($key, ['instanceOf' => $value]);
                }
            } elseif (is_object($value)) {
                if (!$this->Dice->hasShared($key) || $this->Dice->create($key) !== $value) {
                    $this->Dice = $this->Dice->addShared($key, $value);
                }
            } elseif (($this->Dice->getDefaultRule()['substitutions'][ltrim($key, '\\')] ?? null) !== $value) {
                // If this substitution can't be converted to a rule, copy it to
                // the default rule and force Dice to use it for the given
                // identifier
                $defaultRule['substitutions'][$key] = $value;
                $this->Dice                         = $this->Dice->removeRule($key);
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
     * @return $this
     */
    private function _bind(string $id, ?string $instanceOf, ?array $constructParams, ?array $shareInstances, array $rule = [])
    {
        if (!is_null($instanceOf)) {
            $rule['instanceOf'] = $instanceOf;
        }
        if (!is_null($constructParams)) {
            $rule['constructParams'] = $constructParams;
        }
        if (!is_null($shareInstances)) {
            $rule['shareInstances'] = array_merge($rule['shareInstances'] ?? [], $shareInstances);
        }
        $this->addRule($id, $rule);

        return $this;
    }

    /**
     * @return $this
     */
    final public function bind(string $id, ?string $instanceOf = null, ?array $constructParams = null, ?array $shareInstances = null)
    {
        return $this->_bind($id, $instanceOf, $constructParams, $shareInstances);
    }

    /**
     * @return $this
     */
    final public function bindIf(string $id, ?string $instanceOf = null, ?array $constructParams = null, ?array $shareInstances = null)
    {
        if (!$this->Dice->hasRule($id)) {
            return $this->_bind($id, $instanceOf, $constructParams, $shareInstances);
        }

        return $this;
    }

    /**
     * @return $this
     */
    final public function singleton(string $id, ?string $instanceOf = null, ?array $constructParams = null, ?array $shareInstances = null)
    {
        return $this->_bind($id, $instanceOf, $constructParams, $shareInstances, ['shared' => true]);
    }

    /**
     * @return $this
     */
    final public function singletonIf(string $id, ?string $instanceOf = null, ?array $constructParams = null, ?array $shareInstances = null)
    {
        if (!$this->Dice->hasRule($id)) {
            return $this->_bind($id, $instanceOf, $constructParams, $shareInstances, ['shared' => true]);
        }

        return $this;
    }

    /**
     * @param string[]|null $services
     * @param string[]|null $exceptServices
     * @return $this
     */
    final public function service(string $id, ?array $services = null, ?array $exceptServices = null, int $lifetime = ServiceLifetime::INHERIT)
    {
        if (!is_subclass_of($id, IService::class)) {
            throw new UnexpectedValueException($id . ' does not implement ' . IService::class);
        }
        $this->applyService($id, $services, $exceptServices, $lifetime);
        $this->Services[$id] = true;

        return $this;
    }

    private function applyService(string $id, ?array $services = null, ?array $exceptServices = null, int $lifetime = ServiceLifetime::INHERIT): void
    {
        if ($lifetime & ServiceLifetime::INHERIT) {
            $lifetime = 0;
            if (is_a($id, IServiceSingleton::class, true)) {
                $lifetime |= ServiceLifetime::SINGLETON;
            }
            if (is_a($id, IServiceShared::class, true)) {
                $lifetime |= ServiceLifetime::SERVICE_SINGLETON;
            }
        }

        $rule = [];
        if ($lifetime & ServiceLifetime::SINGLETON) {
            $rule['shared'] = true;

            // If SINGLETON and SERVICE_SINGLETON are both set, disable
            // inheritance to keep service instances separate
            if ($lifetime & ServiceLifetime::SERVICE_SINGLETON) {
                $rule['inherit'] = false;
            }
        }
        if ($bindings = $id::getContextualBindings()) {
            $rule['substitutions'] = $bindings;
        }
        if ($rule) {
            $this->addRule($id, $rule);
        }

        $bind = $id::getServices();
        if (!is_null($services)) {
            $bind = array_intersect($bind, $services = array_unique($services));
            if (count($bind) < count($services)) {
                throw new UnexpectedValueException($id . ' does not implement: ' . implode(', ', array_diff($services, $bind)));
            }
        }
        if (!is_null($exceptServices)) {
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
     * @return $this
     */
    final public function instance(string $id, $instance)
    {
        $this->Dice = $this->Dice->addShared($id, $instance);

        return $this;
    }

    /**
     * @return $this
     */
    final public function instanceIf(string $id, $instance)
    {
        if (!$this->Dice->hasRule($id)) {
            return $this->instance($id, $instance);
        }

        return $this;
    }

    /**
     * @return $this
     */
    final public function services(array $serviceMap, int $lifetime = ServiceLifetime::INHERIT)
    {
        $idMap = [];
        foreach ($serviceMap as $id => $instanceOf) {
            $idMap[$instanceOf][] = $id;
        }

        foreach ($idMap as $instanceOf => $services) {
            $this->service($instanceOf, $services, null, $lifetime);
        }

        return $this;
    }

    final public function getServices(): array
    {
        return array_keys($this->Services);
    }
}
