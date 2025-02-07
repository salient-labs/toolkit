<?php declare(strict_types=1);

namespace Salient\Core\Facade;

use Salient\Core\ErrorHandler;

/**
 * A facade for ErrorHandler
 *
 * @method static ErrorHandler deregister() Deregister previously registered error and exception handlers
 * @method static int getExitStatus() Get the exit status of the running script if it is terminating
 * @method static void handleExitSignal(int $exitStatus) Report the exit status of the running script before it terminates on SIGTERM, SIGINT or SIGHUP
 * @method static bool isRegistered() Check if error, exception and shutdown handlers are registered
 * @method static bool isShuttingDown() Check if the running script is terminating
 * @method static bool isShuttingDownOnError() Check if the running script is terminating after a fatal error, uncaught exception or exit signal
 * @method static ErrorHandler register() Register error, exception and shutdown handlers
 * @method static ErrorHandler silencePath(string $path, int $levels = 24576) Silence errors in a file or directory
 * @method static ErrorHandler silencePattern(string $pattern, int $levels = 24576) Silence errors in paths that match a regular expression
 *
 * @extends Facade<ErrorHandler>
 *
 * @generated
 */
final class Err extends Facade
{
    /**
     * @internal
     */
    protected static function getService()
    {
        return ErrorHandler::class;
    }
}
