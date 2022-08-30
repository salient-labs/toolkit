<?php

declare(strict_types=1);

namespace Lkrms\Facade;

use Lkrms\Concept\Facade;
use Lkrms\Container\Container;
use Lkrms\Contract\IContainer;

/**
 * A facade for Container
 *
 * @method static Container load() Create and return the underlying Container
 * @method static Container getInstance() Return the underlying Container
 * @method static bool isLoaded() Return true if the underlying Container has been created
 * @method static Container bind(string $id, ?string $instanceOf = null, ?array $constructParams = null, ?array $shareInstances = null, array $customRule = [])
 * @method static mixed get(string $id, mixed ...$params)
 * @method static IContainer getGlobalContainer()
 * @method static string getName(string $id)
 * @method static bool has(string $id)
 * @method static bool hasGlobalContainer()
 * @method static Container inContextOf(string $id)
 * @method static Container service(string $id, null|string[] $services = null, null|string[] $exceptServices = null)
 * @method static IContainer|null setGlobalContainer(?IContainer $container)
 * @method static Container singleton(string $id, ?string $instanceOf = null, ?array $constructParams = null, ?array $shareInstances = null, array $customRule = [])
 *
 * @uses Container
 * @lkrms-generate-command lk-util generate facade --class='Lkrms\Container\Container' --generate='Lkrms\Facade\DI'
 */
final class DI extends Facade
{
    /**
     * @internal
     */
    protected static function getServiceName(): string
    {
        return Container::class;
    }
}
