<?php

declare(strict_types=1);

namespace Lkrms\Container;

use Dice\Dice;
use Lkrms\Core\Contract\ConstructorHasNoRequiredParameters;
use Psr\Container\ContainerInterface;
use RuntimeException;
use UnexpectedValueException;

/**
 * A stackable dependency injection container
 *
 * Mostly a PSR-11 wrapper for Dice.
 *
 * @link https://r.je/dice Dice home page
 * @link https://github.com/Level-2/Dice Dice repository on GitHub
 */
class Container implements ContainerInterface, ConstructorHasNoRequiredParameters
{
    /**
     * @var Container|null
     */
    private static $Instance;

    /**
     * @var Dice|null
     */
    private $Dice;

    /**
     * @var Dice[]
     */
    private $DiceStack = [];

    public function __construct()
    {
        $this->bindContainer($this);
    }

    public function __clone()
    {
        $this->bindContainer($this);
    }

    /**
     * Bind this instance to another for service container injection
     *
     * This function can be used to prevent temporary service containers binding
     * instances they create to themselves. See
     * {@see SyncProvider::invokeInBoundContainer()} for an example.
     *
     * @param Container $container The container that should resolve compatible
     * requests to this instance.
     */
    final public function bindContainer(Container $container)
    {
        $subs  = [ContainerInterface::class => $this];
        $class = static::class;
        do
        {
            $subs[$class] = $this;
        }
        while (self::class != $class && ($class = get_parent_class($class)));
        $container->addRule("*", ["substitutions" => $subs]);
    }

    /**
     * Returns true if a global container exists
     *
     * @return bool
     */
    final public static function hasGlobal(): bool
    {
        return !is_null(self::$Instance);
    }

    /**
     * Get the current global container, creating one if necessary
     *
     * @return Container
     */
    final public static function getGlobal(): Container
    {
        if (!is_null(self::$Instance))
        {
            return self::$Instance;
        }

        return self::$Instance = new static(...func_get_args());
    }

    private function dice(): Dice
    {
        return $this->Dice ?: ($this->Dice = new Dice());
    }

    /**
     * Get a fully constructed object for the given identifier
     *
     * @param string $id Class or interface to resolve.
     * @param mixed ...$params Parameters to pass to the constructor if creating
     * a new instance.
     * @return mixed
     */
    public function get(string $id, ...$params)
    {
        return $this->dice()->create($id, $params);
    }

    /**
     * Get a concrete class name for the given identifier
     *
     * Returns `$id` if no entries for it are bound to the container.
     *
     * @param string $id Class or interface to resolve.
     * @return string
     */
    public function name(string $id): string
    {
        return $this->dice()->getRule($id)["instanceOf"] ?? $id;
    }

    /**
     * Returns true if the given identifier can be resolved to a concrete class
     */
    public function has(string $id): bool
    {
        return class_exists($this->name($id));
    }

    /**
     * Push a copy of the container onto the stack
     */
    public function push(): void
    {
        $this->DiceStack[] = clone $this->dice();
    }

    /**
     * Pop the most recently pushed container off the stack and activate it
     *
     * @throws RuntimeException if the container stack is empty
     */
    public function pop(): void
    {
        if (is_null($dice = array_pop($this->DiceStack)))
        {
            throw new RuntimeException("Container stack is empty");
        }

        $this->Dice = $dice;
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

    private function addRule(string $id, array $rule): void
    {
        $dice = $this->dice()->addRule($id, $rule);
        self::checkRule($dice->getRule($id));
        $this->Dice = $dice;
    }

    /**
     * Bind a class to the given identifier
     *
     * When the container needs an instance of `$id`, create a new
     * `$instanceOf`, passing any `$constructParams` to its constructor and only
     * creating one instance of any classes named in `$shareInstances`.
     *
     * @param string $id
     * @param string|null $instanceOf Default: `$id`
     * @param array|null $constructParams
     * @param array|null $shareInstances
     * @param array $customRule Dice-compatible rules may be given here.
     */
    public function bind(
        string $id,
        string $instanceOf     = null,
        array $constructParams = null,
        array $shareInstances  = null,
        array $customRule      = []
    ): void
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
    }

    /**
     * Bind a class to the given identifier as a shared dependency
     *
     * When the container needs an instance of `$id`, use a previously created
     * instance if possible, otherwise create a new `$instanceOf` as per
     * {@see Container::bind()}, and store it for use with subsequent requests
     * for `$id`.
     *
     * @param string $id
     * @param string|null $instanceOf Default: `$id`
     * @param array|null $constructParams
     * @param array|null $shareInstances
     * @param array $customRule Dice-compatible rules may be given here.
     */
    public function singleton(
        string $id,
        string $instanceOf     = null,
        array $constructParams = null,
        array $shareInstances  = null,
        array $customRule      = []
    ): void
    {
        $customRule["shared"] = true;
        $this->bind(
            $id,
            $instanceOf,
            $constructParams,
            $shareInstances,
            $customRule
        );
    }
}
