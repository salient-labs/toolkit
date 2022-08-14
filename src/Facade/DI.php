<?php

declare(strict_types=1);

namespace Lkrms\Facade;

use Lkrms\Concept\Facade;
use Lkrms\Container\Container;

/**
 * A facade for Container
 *
 * @method static Container load()
 * @method static $this bind(string $id, ?string $instanceOf = null, ?array $constructParams = null, ?array $shareInstances = null, array $customRule = [])
 * @method static void bindContainer(Container $container)
 * @method static mixed get(string $id, mixed ...$params)
 * @method static Container getGlobal()
 * @method static bool has(string $id)
 * @method static bool hasGlobal()
 * @method static string name(string $id)
 * @method static $this pop()
 * @method static $this push()
 * @method static $this singleton(string $id, ?string $instanceOf = null, ?array $constructParams = null, ?array $shareInstances = null, array $customRule = [])
 *
 * @uses Container
 * @lkrms-generate-command lk-util generate facade --class='Lkrms\Container\Container' --generate='Lkrms\Facade\DI'
 */
final class DI extends Facade
{
    protected static function getServiceName(): string
    {
        return Container::class;
    }
}
