<?php declare(strict_types=1);

namespace Lkrms\Facade;

use Lkrms\Concept\Facade;
use Lkrms\Support\ErrorHandler;

/**
 * A facade for \Lkrms\Support\ErrorHandler
 *
 * @method static bool isLoaded() True if the facade's underlying instance is loaded
 * @method static void load(ErrorHandler|null $instance = null) Load the facade's underlying instance
 * @method static void swap(ErrorHandler $instance) Replace the facade's underlying instance
 * @method static void unload() Remove the facade's underlying instance if loaded
 * @method static ErrorHandler getInstance() Get the facade's underlying instance, loading it if necessary
 * @method static ErrorHandler deregister(bool $unloadFacades = true) Deregister previously registered error and exception handlers
 * @method static bool isRegistered() True if error, exception and shutdown handlers are registered
 * @method static ErrorHandler register() Register error, exception and shutdown handlers
 * @method static ErrorHandler silencePath(string $path, int $levels = 26624) Silence errors in a file or directory
 * @method static ErrorHandler silencePattern(string $pattern, int $levels = 26624) Silence errors in paths that match a regular expression
 *
 * @extends Facade<ErrorHandler>
 *
 * @generated
 */
final class Err extends Facade
{
    /**
     * @inheritDoc
     */
    protected static function getService(): string
    {
        return ErrorHandler::class;
    }
}
