<?php

declare(strict_types=1);

namespace Lkrms\Facade;

use Lkrms\Concept\Facade;
use Lkrms\Container\AppContainer;
use Lkrms\Container\Container;

/**
 * A facade for AppContainer
 *
 * @uses AppContainer
 *
 * @method static AppContainer load(?string $basePath = null)
 * @method static void bind(string $id, ?string $instanceOf = null, ?array $constructParams = null, ?array $shareInstances = null, array $customRule = [])
 * @method static mixed bindContainer(Container $container)
 * @method static AppContainer enableCache()
 * @method static mixed get(string $id, ...$params)
 * @method static Container getGlobal()
 * @method static bool has(string $id)
 * @method static bool hasCacheStore()
 * @method static bool hasGlobal()
 * @method static string name(string $id)
 * @method static void pop()
 * @method static void push()
 * @method static void singleton(string $id, ?string $instanceOf = null, ?array $constructParams = null, ?array $shareInstances = null, array $customRule = [])
 */
final class App extends Facade
{
    protected static function getServiceName(): string
    {
        return AppContainer::class;
    }
}
