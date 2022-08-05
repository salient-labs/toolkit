<?php

declare(strict_types=1);

namespace Lkrms\Facade;

use Lkrms\Concept\Facade;
use Lkrms\Container\Container;

/**
 * A facade for Container
 *
 * @uses Container
 *
 * @method static Container load()
 * @method static void bind(string $id, ?string $instanceOf = null, ?array $constructParams = null, ?array $shareInstances = null, array $customRule = [])
 * @method static mixed bindContainer(Container $container)
 * @method static mixed get(string $id, mixed ...$params)
 * @method static Container getGlobal()
 * @method static bool has(string $id)
 * @method static bool hasGlobal()
 * @method static string name(string $id)
 * @method static void pop()
 * @method static void push()
 * @method static void singleton(string $id, ?string $instanceOf = null, ?array $constructParams = null, ?array $shareInstances = null, array $customRule = [])
 */
final class DI extends Facade
{
    protected static function getServiceName(): string
    {
        return Container::class;
    }

    /**
     * @deprecated
     */
    public static function create(string $name, array $params = null)
    {
        if (is_null($params))
        {
            $params = [];
        }

        return self::get($name, ...$params);
    }

    /**
     * @deprecated
     */
    public static function resolve(string $name): string
    {
        return self::name($name);
    }

    /**
     * @deprecated
     */
    public static function register(
        string $name,
        string $instanceOf     = null,
        array $constructParams = null,
        array $shareInstances  = null,
        array $customRule      = []
    ): void
    {
        self::bind($name, $instanceOf, $constructParams, $shareInstances, $customRule);
    }

    /**
     * @deprecated
     */
    public static function registerSingleton(
        string $name,
        string $instanceOf     = null,
        array $constructParams = null,
        array $shareInstances  = null,
        array $customRule      = []
    ): void
    {
        self::singleton($name, $instanceOf, $constructParams, $shareInstances, $customRule);
    }
}
