<?php

declare(strict_types=1);

namespace Lkrms\Facade;

use Lkrms\Concept\Facade;
use Lkrms\Container\AppContainer;
use Lkrms\Container\Container;
use Lkrms\Contract\IContainer;

/**
 * A facade for AppContainer
 *
 * @method static AppContainer load(?string $basePath = null) Create and return the underlying AppContainer
 * @method static AppContainer getInstance() Return the underlying AppContainer
 * @method static bool isLoaded() Return true if the underlying AppContainer has been created
 * @method static AppContainer bind(string $id, ?string $instanceOf = null, ?array $constructParams = null, ?array $shareInstances = null, array $customRule = [])
 * @method static AppContainer enableCache()
 * @method static AppContainer enableExistingCache()
 * @method static AppContainer enableMessageLog(?string $name = null, array $levels = \Lkrms\Console\ConsoleLevels::ALL_DEBUG)
 * @method static mixed get(string $id, mixed ...$params)
 * @method static IContainer getGlobalContainer()
 * @method static string getName(string $id)
 * @method static bool has(string $id)
 * @method static bool hasGlobalContainer()
 * @method static Container inContextOf(string $id)
 * @method static AppContainer service(string $id, null|string[] $services = null, null|string[] $exceptServices = null)
 * @method static IContainer|null setGlobalContainer(?IContainer $container)
 * @method static AppContainer singleton(string $id, ?string $instanceOf = null, ?array $constructParams = null, ?array $shareInstances = null, array $customRule = [])
 *
 * @uses AppContainer
 * @lkrms-generate-command lk-util generate facade --class='Lkrms\Container\AppContainer' --generate='Lkrms\Facade\App'
 */
final class App extends Facade
{
    /**
     * @internal
     */
    protected static function getServiceName(): string
    {
        return AppContainer::class;
    }
}
