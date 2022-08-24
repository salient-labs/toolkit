<?php

declare(strict_types=1);

namespace Lkrms\Container;

use Dice\Dice;
use Lkrms\Container\ContextContainer;
use Lkrms\Contract\HasNoRequiredConstructorParameters;
use Lkrms\Contract\IBindable;
use Lkrms\Contract\IBindableSingleton;
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
     * @var Container|null
     */
    protected $BackingContainer;

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

        $this->bindTo($this);
    }

    public function __clone()
    {
        $this->bindTo($this);
    }

    /**
     * @internal
     */
    protected function bindTo(Container $container): void
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

        $class = static::class;
        if ($class === ContextContainer::class)
        {
            $class = self::class;
        }

        self::$CreatingInstance = true;
        $instance = new $class(...func_get_args());

        if ($instance !== self::$Instance)
        {
            throw new RuntimeException("Error creating global container");
        }

        return $instance;
    }

    private function me(): Container
    {
        return $this->BackingContainer ?: $this;
    }

    protected function dice(): Dice
    {
        return $this->me()->Dice ?: ($this->me()->Dice = new Dice());
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
        $this->me()->DiceStack[] = clone $this->dice();
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
        if (is_null($dice = array_pop($this->me()->DiceStack)))
        {
            throw new RuntimeException("Container stack is empty");
        }

        $this->me()->Dice = $dice;
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

    protected function addRule(string $id, array $rule): void
    {
        $dice = $this->dice()->addRule($id, $rule);
        self::checkRule($dice->getRule($id));
        $this->me()->Dice = $dice;
    }

    /**
     * Get a context-specific facade for the container
     *
     * Returns a {@see ContextContainer} that surfaces the container's usual
     * methods in the context of the given identifier, allowing contextual
     * bindings to be applied in scenarios other than dependency injection.
     */
    public function context(string $id): ContextContainer
    {
        return ContextContainer::create($this->me(), $id);
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
     * Bind a class to the given identifier as a shared instance
     *
     * When the container needs an instance of `$id`, use a previously created
     * instance if possible, otherwise create a new `$instanceOf` as per
     * {@see Container::bind()}, and store it for use with subsequent requests.
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

    /**
     * Bind an IBindable and its services, optionally specifying the services to
     * bind or exclude
     *
     * If `$id` implements {@see IBindableSingleton}, bind it as a shared
     * instance.
     *
     * @param string[] $services
     * @param string[] $exceptServices
     * @return $this
     */
    public function bindable(string $id, ?array $services = null, ?array $exceptServices = null)
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

    /**
     * Identical to bindable()
     *
     * @param string[] $services
     * @param string[] $exceptServices
     * @return $this
     * @see Container::bindable()
     */
    public function service(string $id, ?array $services = null, ?array $exceptServices = null)
    {
        return $this->bindable($id, $services, $exceptServices);
    }
}
