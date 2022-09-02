<?php

declare(strict_types=1);

namespace Lkrms\Container;

use Dice\Dice;
use Lkrms\Contract\IBindable;
use Lkrms\Contract\IBindableSingleton;
use Lkrms\Contract\IContainer;
use Lkrms\Contract\ReceivesContainer;
use Psr\Container\ContainerInterface;
use UnexpectedValueException;

/**
 * A service container
 *
 * Uses Dice under the hood.
 *
 * @link https://r.je/dice Dice home page
 * @link https://github.com/Level-2/Dice Dice repository on GitHub
 */
class Container implements IContainer, ContainerInterface
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
     * @var null|string
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
     * When an IBindable class is bound to the container,
     * `$this->ServiceStack[$name] = true` is applied
     *
     * @var array<string,true>
     */
    private $ServiceStack = [];

    public function __construct()
    {
        $this->load();
    }

    private function __clone()
    {
    }

    private function load(): void
    {
        $dice  = & $this->dice();
        $dice  = $dice->addShared(ContainerInterface::class, $this);
        $class = static::class;
        do
        {
            $dice = $dice->addShared($class, $this);
        }
        while (self::class != $class && ($class = get_parent_class($class)));
        $dice = $dice->addCallback(
            "*",
            function (object $instance)
            {
                if ($instance instanceof ReceivesContainer)
                {
                    $instance->setContainer($this);
                }
                return $instance;
            }
        );
    }

    final public static function hasGlobalContainer(): bool
    {
        return !is_null(self::$GlobalContainer);
    }

    final public static function getGlobalContainer(): IContainer
    {
        if (!is_null(self::$GlobalContainer))
        {
            return self::$GlobalContainer;
        }

        return self::$GlobalContainer = new static(...func_get_args());
    }

    final public static function setGlobalContainer(?IContainer $container): ?IContainer
    {
        return self::$GlobalContainer = $container;
    }

    private function & dice(): Dice
    {
        if (!$this->Dice)
        {
            $this->Dice = new Dice();
        }
        return $this->Dice;
    }

    public function get(string $id, ...$params)
    {
        return $this->dice()->create($id, $params);
    }

    public function getName(string $id): string
    {
        return $this->dice()->getRule($id)["instanceOf"] ?? $id;
    }

    public function has(string $id): bool
    {
        return class_exists($this->getName($id));
    }

    private function checkRule(array $rule): void
    {
        if (!empty(
            $subs = array_intersect(
                $rule["shareInstances"] ?? [],
                array_keys($rule["substitutions"] ?? [])
            )
        ))
        {
            throw new UnexpectedValueException("Dependencies in 'shareInstances' cannot be substituted: " . implode(", ", $subs));
        }
    }

    private function addRule(string $id, array $rule, Dice & $dice = null): void
    {
        if (is_null($dice))
        {
            $dice = & $this->dice();
        }

        $_dice = $dice->addRule($id, $rule);
        $this->checkRule($_dice->getRule($id));
        $dice = $_dice;
    }

    private function applyBindings(array $subs, Dice & $dice = null): void
    {
        if (is_null($dice))
        {
            $dice = & $this->dice();
        }

        $defaultRule = [];
        foreach ($subs as $key => $value)
        {
            if (is_string($value))
            {
                if (strcasecmp($dice->getRule($key)["instanceOf"] ?? "", $value))
                {
                    $this->addRule($key, ["instanceOf" => $value], $dice);
                }
            }
            elseif (is_object($value))
            {
                if (!$dice->hasShared($key) || $dice->create($key) !== $value)
                {
                    $dice = $dice->addShared($key, $value);
                }
            }
            elseif (($dice->getDefaultRule()["substitutions"][ltrim($key, '\\')] ?? null) !== $value)
            {
                // If this substitution can't be converted to a rule, copy it to
                // the default rule and force Dice to use it for the given
                // identifier
                $defaultRule["substitutions"][$key] = $value;
                $dice = $dice->removeRule($key);
            }
        }
        if (!empty($defaultRule))
        {
            /**
             * @todo Patch Dice to apply substitutions in `create()`, not just
             * when resolving dependencies
             */
            $this->addRule("*", $defaultRule, $dice);
        }
    }

    public function inContextOf(string $id): Container
    {
        $dice = $cleanDice = $this->dice();

        // If $id implements IBindable and hasn't been bound to the container
        // yet, add bindings for everything except its services, which may
        // resolve to another provider
        $serviceApplied = false;
        if (is_subclass_of($id, IBindable::class) && !($this->ServiceStack[$id] ?? null))
        {
            $this->applyService($id, [], null, $dice);
            // If nothing changed, add $id to the current service stack,
            // otherwise add it to the new container's stack
            if ($dice === $cleanDice)
            {
                $this->ServiceStack[$id] = true;
            }
            else
            {
                $serviceApplied = true;
            }
        }

        if (!$dice->hasRule($id) ||
            empty($subs = $dice->getRule($id)["substitutions"] ?? null))
        {
            return $this;
        }

        $this->applyBindings($subs, $dice);

        if ($dice === $cleanDice)
        {
            return $this;
        }

        $instance          = clone $this;
        $instance->Dice    = $dice;
        $instance->Context = $instance->ContextStack[] = $id;
        if ($serviceApplied) { $instance->ServiceStack[$id] = true; }
        $instance->load();
        return $instance;
    }

    private function _bind(string $id, ?string $instanceOf, ?array $constructParams, ?array $shareInstances, array $rule = []): void
    {
        if (!is_null($instanceOf))
        {
            $rule["instanceOf"] = $instanceOf;
        }
        if (!is_null($constructParams))
        {
            $rule["constructParams"] = $constructParams;
        }
        if (!is_null($shareInstances))
        {
            $rule["shareInstances"] = array_merge($rule["shareInstances"] ?? [], $shareInstances);
        }
        $this->addRule($id, $rule);
    }

    /**
     * @return $this
     */
    public function bind(string $id, ?string $instanceOf = null, ?array $constructParams = null, ?array $shareInstances = null)
    {
        $this->_bind($id, $instanceOf, $constructParams, $shareInstances);
        return $this;
    }

    /**
     * @return $this
     */
    public function singleton(string $id, ?string $instanceOf = null, ?array $constructParams = null, ?array $shareInstances = null)
    {
        $this->_bind($id, $instanceOf, $constructParams, $shareInstances, ["shared" => true]);
        return $this;
    }

    /**
     * @param null|string[] $services
     * @param null|string[] $exceptServices
     * @return $this
     */
    public function service(string $id, ?array $services = null, ?array $exceptServices = null, ?array $constructParams = null, ?array $shareInstances = null)
    {
        if (!is_subclass_of($id, IBindable::class))
        {
            throw new UnexpectedValueException($id . " does not implement " . IBindable::class);
        }
        $this->applyService($id, $services, $exceptServices);
        $this->ServiceStack[$id] = true;
        return $this;
    }

    private function applyService(string $id, ?array $services = null, ?array $exceptServices = null, Dice & $dice = null): void
    {
        if (is_null($dice))
        {
            $dice = & $this->dice();
        }

        if (is_subclass_of($id, IBindableSingleton::class))
        {
            $this->addRule($id, ["shared" => true], $dice);
        }

        $bindable = $id::getBindable();
        if (!is_null($services))
        {
            if (count($bindable = array_intersect($bindable, $services)) < count($services))
            {
                throw new UnexpectedValueException($id . " does not implement: " . implode(", ", array_diff($services, $bindable)));
            }
        }
        if (!is_null($exceptServices))
        {
            $bindable = array_diff($bindable, $exceptServices);
        }
        foreach ($bindable as $service)
        {
            $this->addRule($service, ["instanceOf" => $id], $dice);
        }

        if ($subs = $id::getBindings())
        {
            $this->addRule($id, ["substitutions" => $subs], $dice);
        }
    }

    /**
     * @return $this
     */
    public function instance(string $id, $instance)
    {
        $dice = & $this->dice();
        $dice = $dice->addShared($id, $instance);
        return $this;
    }

}
