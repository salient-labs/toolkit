<?php

declare(strict_types=1);

namespace Lkrms\Facade;

use Lkrms\Concept\Facade;
use Lkrms\Container\AppContainer;
use Lkrms\Container\Container;

/**
 * A facade for AppContainer
 *
 * @method static AppContainer load(?string $basePath = null) Create and return the underlying AppContainer
 * @method static AppContainer getInstance() Return the underlying AppContainer
 * @method static bool isLoaded() Return true if the underlying AppContainer has been created
 * @method static AppContainer bind(string $id, ?string $instanceOf = null, ?array $constructParams = null, ?array $shareInstances = null, array $customRule = []) Bind a class to the given identifier
 * @method static void bindContainer(Container $container) Bind this instance to another for service container injection
 * @method static AppContainer enableCache()
 * @method static AppContainer enableExistingCache()
 * @method static AppContainer enableMessageLog(?string $name = null, array $levels = \Lkrms\Console\ConsoleLevels::ALL_DEBUG)
 * @method static mixed get(string $id, mixed ...$params) Create a new instance of the given class or interface, or retrieve a singleton created earlier
 * @method static Container getGlobal() Get the current global container, creating one if necessary
 * @method static bool has(string $id) Returns true if the given identifier can be resolved to a concrete class
 * @method static bool hasGlobal() Returns true if a global container exists
 * @method static string name(string $id) Get a concrete class name for the given identifier
 * @method static AppContainer pop() Pop the most recently pushed container off the stack and activate it
 * @method static AppContainer push() Push a copy of the container onto the stack
 * @method static AppContainer singleton(string $id, ?string $instanceOf = null, ?array $constructParams = null, ?array $shareInstances = null, array $customRule = []) Bind a class to the given identifier as a shared dependency
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
