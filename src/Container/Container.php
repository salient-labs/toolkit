<?php

declare(strict_types=1);

namespace Lkrms\Container;

use Dice\Dice;
use Lkrms\Contract\HasNoRequiredConstructorParameters;
use Psr\Container\ContainerInterface;
use RuntimeException;
use UnexpectedValueException;

/**
 * A stackable service container
 *
 * Uses Dice under the hood.
 *
 * @link https://r.je/dice Dice home page
 * @link https://github.com/Level-2/Dice Dice repository on GitHub
 */
class Container implements ContainerInterface, HasNoRequiredConstructorParameters
{
    /**
     * @var bool|null
     */
    private static $CreatingInstance;

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
        if (self::$CreatingInstance)
        {
            self::$CreatingInstance = null;
            self::$Instance         = $this;
        }

        $this->bindContainer($this);
    }

    public function __clone()
    {
        $this->bindContainer($this);
    }

    /**
     * Bind this instance to another for service container injection
     *
     * This function is used to prevent temporary service containers binding
     * instances they create to themselves. See
     * {@see \Lkrms\Concern\TBindable::invokeInBoundContainer()} for an example.
     *
     * @internal
     * @param Container $container The container that should resolve compatible
     * requests to this instance.
     */
    final public function bindContainer(Container $container): void
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

        self::$CreatingInstance = true;
        $instance = new static(...func_get_args());

        if ($instance !== self::$Instance)
        {
            throw new RuntimeException("Error creating global container");
        }

        return $instance;
    }

    private function dice(): Dice
    {
        return $this->Dice ?: ($this->Dice = new Dice());
    }

    /**
     * Create a new instance of the given class or interface, or retrieve a
     * singleton created earlier
     *
     * @template T
     * @psalm-param class-string<T> $id
     * @psalm-return T
     * @param ...$params Values to pass to the constructor of the concrete class
     * bound to `$id`. Ignored if `$id` resolves to an existing singleton.
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
     *
     * @return $this
     */
    public function push()
    {
        $this->DiceStack[] = clone $this->dice();
        return $this;
    }

    /**
     * Pop the most recently pushed container off the stack and activate it
     *
     * @return $this
     * @throws RuntimeException if the container stack is empty
     */
    public function pop()
    {
        if (is_null($dice = array_pop($this->DiceStack)))
        {
            throw new RuntimeException("Container stack is empty");
        }

        $this->Dice = $dice;
        return $this;
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
     * When the container needs an instance of `$id`, create a new `$instanceOf`
     * (default: `$id`), passing any `$constructParams` to its constructor and
     * only creating one instance of any classes named in `$shareInstances`.
     *
     * @param array $customRule Dice-compatible rules may be given here.
     * @return $this
     */
    public function bind(
        string $id,
        string $instanceOf     = null,
        array $constructParams = null,
        array $shareInstances  = null,
        array $customRule      = []
    ) {
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
     * Bind a class to the given identifier as a shared dependency
     *
     * When the container needs an instance of `$id`, use a previously created
     * instance if possible, otherwise create a new `$instanceOf` as per
     * {@see Container::bind()}, and store it for use with subsequent requests
     * for `$id`.
     *
     * @param array $customRule Dice-compatible rules may be given here.
     * @return $this
     */
    public function singleton(
        string $id,
        string $instanceOf     = null,
        array $constructParams = null,
        array $shareInstances  = null,
        array $customRule      = []
    ) {
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
}
