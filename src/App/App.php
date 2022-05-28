<?php

declare(strict_types=1);

namespace Lkrms\App;

use Lkrms\Core\Facade;

/**
 * A facade for AppContainer
 *
 * @uses AppContainer
 *
 * @method static AppContainer load(string $basePath = null, string|string[]|null $silenceErrorsInPaths = null)
 * @method static AppContainer enableCache()
 */
final class App extends Facade
{
    protected static function getServiceName(): string
    {
        return AppContainer::class;
    }
}
