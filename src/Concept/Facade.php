<?php declare(strict_types=1);

namespace Lkrms\Concept;

use Lkrms\Container\Container;
use Lkrms\Contract\IFacade;
use Lkrms\Contract\ReceivesFacade;
use RuntimeException;

/**
 * A static interface to an instance of an underlying class
 *
 * @template TClass of object
 * @implements IFacade<TClass>
 */
abstract class Facade implements IFacade
{
    /**
     * Get the name of the underlying class
     *
     * @return class-string<TClass>
     */
    abstract protected static function getServiceName(): string;

    /**
     * @var array<string,object>
     */
    private static $Instances = [];

    /**
     * @var array<string,array<string,int|null>>
     */
    private static $FuncNumArgs = [];

    /**
     * @return TClass
     */
    private static function _load()
    {
        $service = static::getServiceName();

        if (Container::hasGlobalContainer()) {
            $container = Container::getGlobalContainer();
            $instance  = $container->singletonIf($service)
                                   ->get($service, func_get_args());
        } else {
            $instance = new $service(...func_get_args());
        }

        if ($instance instanceof ReceivesFacade) {
            $instance->setFacade(static::class);
        }

        return self::$Instances[static::class] = $instance;
    }

    /**
     * @internal
     */
    final public static function isLoaded(): bool
    {
        return isset(self::$Instances[static::class]);
    }

    /**
     * @internal
     * @return TClass
     */
    final public static function load()
    {
        if (self::$Instances[static::class] ?? null) {
            throw new RuntimeException(static::class . ' already loaded');
        }

        return self::_load(...func_get_args());
    }

    /**
     * @internal
     */
    final public static function unload(): void
    {
        unset(self::$Instances[static::class]);
    }

    /**
     * Get the number of arguments received by the facade for the current
     * invocation of a method
     *
     * If a method is called via a facade, the return value of `func_num_args()`
     * may not reflect the original invocation. For a more reliable result, call
     * {@see Facade::getFuncNumArgs()} as early as possible in the method.
     *
     * In this example, the class implements {@see ReceivesFacade} and sets
     * `$this->Facade` when {@see ReceivesFacade::setFacade()} is called:
     *
     * ```php
     * public function doSomething(string $arg1 = '', ?int $arg2 = null)
     * {
     *     !is_null($numArgs = $this->Facade
     *             ? $this->Facade::getFuncNumArgs(__FUNCTION__)
     *             : null) ||
     *         $numArgs = func_num_args();
     *
     *     // ...
     * }
     * ```
     *
     * {@see Facade::getFuncNumArgs()} returns `null` if `$function` is not
     * running via the facade or if {@see Facade::getFuncNumArgs()} has already
     * been called with `$selfDestruct` = `true` (the default) during the
     * current invocation.
     */
    final public static function getFuncNumArgs(string $function, bool $selfDestruct = true): ?int
    {
        $args = self::$FuncNumArgs[static::class][$function] ?? null;
        is_null($args) || !$selfDestruct ||
            self::$FuncNumArgs[static::class][$function] = null;

        return $args;
    }

    /**
     * Clear the underlying instances of all facades
     */
    final public static function unloadAll(): void
    {
        self::$Instances = [];
    }

    /**
     * @internal
     * @return TClass
     */
    final public static function getInstance()
    {
        return self::$Instances[static::class] ?? self::_load();
    }

    /**
     * @internal
     */
    final public static function __callStatic(string $name, array $arguments)
    {
        return (self::$Instances[static::class] ?? self::_load())->$name(...$arguments);
    }

    /**
     * @internal
     */
    final protected static function setFuncNumArgs(string $function, int $funcNumArgs): void
    {
        self::$FuncNumArgs[static::class][$function] = $funcNumArgs;
    }

    /**
     * @internal
     */
    final protected static function clearFuncNumArgs(string $function): void
    {
        self::$FuncNumArgs[static::class][$function] = null;
    }
}
