<?php declare(strict_types=1);

namespace Salient\Core\Facade;

use Salient\Core\AbstractFacade;
use Salient\Core\ErrorHandler;

/**
 * A facade for ErrorHandler
 *
 * @method static ErrorHandler deregister() Deregister previously registered error and exception handlers
 * @method static bool isRegistered() True if error, exception and shutdown handlers are registered
 * @method static ErrorHandler register() Register error, exception and shutdown handlers
 * @method static ErrorHandler silencePath(string $path, int $levels = 26624) Silence errors in a file or directory
 * @method static ErrorHandler silencePattern(string $pattern, int $levels = 26624) Silence errors in paths that match a regular expression
 *
 * @extends AbstractFacade<ErrorHandler>
 *
 * @generated
 */
final class Err extends AbstractFacade
{
    /**
     * @internal
     */
    protected static function getService()
    {
        return ErrorHandler::class;
    }
}
