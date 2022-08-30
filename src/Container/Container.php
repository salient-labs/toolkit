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
        $shared   = $this->dice()->hasShared($id);
        $instance = $this->dice()->create($id, $params);
        if (!$shared && $instance instanceof ReceivesContainer)
        {
            $instance->setContainer($this);
        }
        return $instance;
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

        // Propagating rules to subclasses makes contextual bindings difficult
        // to apply and debug, so disable inheritance
        $rule["inherit"] = false;

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
        $dice = $this->dice();
        if (!$dice->hasRule($id) ||
            empty($subs = $dice->getRule($id)["substitutions"] ?? null))
        {
            return $this;
        }

        $this->applyBindings($subs, $dice);

        if ($dice === $this->dice())
        {
            return $this;
        }

        $instance       = clone $this;
        $instance->Dice = $dice;
        $instance->load();
        return $instance;
    }

    /**
     * @param array $customRule Dice-compatible rules may be given here.
     * @return $this
     */
    public function bind(string $id, string $instanceOf = null, array $constructParams = null, array $shareInstances = null, array $customRule = [])
    {
        $rule = $customRule;
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
        return $this;
    }

    /**
     * @param array $customRule Dice-compatible rules may be given here.
     * @return $this
     */
    public function singleton(string $id, string $instanceOf = null, array $constructParams = null, array $shareInstances = null, array $customRule = [])
    {
        $customRule["shared"] = true;
        $this->bind(
            $id,
            $instanceOf,
            $constructParams,
            $shareInstances,
            $customRule
        );
        return $this;
    }

    /**
     * @param null|string[] $services
     * @param null|string[] $exceptServices
     * @return $this
     */
    public function service(string $id, ?array $services = null, ?array $exceptServices = null)
    {
        if (!is_subclass_of($id, IBindable::class))
        {
            throw new UnexpectedValueException($id . " does not implement " . IBindable::class);
        }

        if (is_subclass_of($id, IBindableSingleton::class))
        {
            $this->singleton($id);
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
            $this->bind($service, $id);
        }

        if ($subs = $id::getBindings())
        {
            $this->addRule($id, ["substitutions" => $subs]);
        }

        return $this;
    }

}
