<?php declare(strict_types=1);

namespace Salient\Core;

use Salient\Contract\Core\Exception\Exception;
use Salient\Contract\Core\Facade\FacadeAwareInterface;
use Salient\Contract\Core\Instantiable;
use Salient\Contract\Core\Unloadable;
use Salient\Core\Concern\FacadeAwareTrait;
use Salient\Core\Facade\Console;
use Salient\Utility\File;
use Salient\Utility\Regex;
use ErrorException;
use LogicException;
use Throwable;

/**
 * @api
 *
 * @implements FacadeAwareInterface<self>
 */
final class ErrorHandler implements FacadeAwareInterface, Instantiable, Unloadable
{
    /** @use FacadeAwareTrait<self> */
    use FacadeAwareTrait;

    private const DEFAULT_EXIT_STATUS = 16;

    private const FATAL_ERRORS =
        \E_ERROR
        | \E_PARSE
        | \E_CORE_ERROR
        | \E_CORE_WARNING
        | \E_COMPILE_ERROR
        | \E_COMPILE_WARNING;

    /**
     * [ [ Path regex, levels ], ... ]
     *
     * @var array<array{string,int}>
     */
    private array $Silenced = [];

    private int $ExitStatus = 0;
    private bool $IsRegistered = false;
    private bool $ShutdownIsRegistered = false;
    private bool $IsShuttingDown = false;
    private bool $IsShuttingDownOnFatalError = false;
    private bool $IsShuttingDownOnUncaughtException = false;
    private bool $IsShuttingDownOnExitSignal = false;

    /**
     * @internal
     */
    public function __construct() {}

    /**
     * @internal
     */
    public function unload(): void
    {
        $this->deregister();
    }

    /**
     * Register error, exception and shutdown handlers
     *
     * @return $this
     */
    public function register(): self
    {
        if ($this->IsRegistered) {
            return $this;
        }

        set_exception_handler([$this, 'handleException']);
        set_error_handler([$this, 'handleError']);

        $this->IsRegistered = true;

        if ($this->ShutdownIsRegistered) {
            return $this;
        }

        register_shutdown_function([$this, 'handleShutdown']);

        $this->ShutdownIsRegistered = true;

        return $this;
    }

    /**
     * Check if error, exception and shutdown handlers are registered
     */
    public function isRegistered(): bool
    {
        return $this->IsRegistered;
    }

    /**
     * Check if the running script is terminating
     */
    public function isShuttingDown(): bool
    {
        return $this->IsShuttingDown;
    }

    /**
     * Check if the running script is terminating after a fatal error, uncaught
     * exception or exit signal
     */
    public function isShuttingDownOnError(): bool
    {
        return $this->IsShuttingDownOnFatalError
            || $this->IsShuttingDownOnUncaughtException
            || $this->IsShuttingDownOnExitSignal;
    }

    /**
     * Get the exit status of the running script if it is terminating
     *
     * @throws LogicException if the running script is not terminating.
     */
    public function getExitStatus(): int
    {
        if (!$this->IsShuttingDown) {
            throw new LogicException('Script is not terminating');
        }
        return $this->ExitStatus;
    }

    /**
     * Deregister previously registered error and exception handlers
     *
     * @return $this
     */
    public function deregister(): self
    {
        if ($this->IsRegistered) {
            restore_error_handler();
            restore_exception_handler();

            $this->IsRegistered = false;
        }

        $this->unloadFacades();

        return $this;
    }

    /**
     * Silence errors in a file or directory
     *
     * @return $this
     */
    public function silencePath(string $path, int $levels = \E_DEPRECATED | \E_USER_DEPRECATED): self
    {
        if (file_exists($path)) {
            $path = File::realpath($path);
            $this->silencePattern(
                '@^' . Regex::quote($path, '@') . (is_dir($path) ? '/' : '$') . '@D',
                $levels,
            );
        }
        return $this;
    }

    /**
     * Silence errors in paths that match a regular expression
     *
     * @return $this
     */
    public function silencePattern(string $pattern, int $levels = \E_DEPRECATED | \E_USER_DEPRECATED): self
    {
        $entry = [$pattern, $levels];
        if (!in_array($entry, $this->Silenced, true)) {
            $this->Silenced[] = $entry;
        }
        return $this;
    }

    /**
     * @internal
     */
    public function handleShutdown(): void
    {
        // Shutdown functions can't be deregistered, so do nothing if this
        // instance has been deregistered
        if (!$this->IsRegistered) {
            return;
        }

        $this->IsShuttingDown = true;

        $error = error_get_last();
        if ($error && ($error['type'] & self::FATAL_ERRORS)) {
            $this->IsShuttingDownOnFatalError = true;
            $this->ExitStatus = 255;
            $this->handleError($error['type'], $error['message'], $error['file'], $error['line']);
        }
    }

    /**
     * @internal
     */
    public function handleError(int $level, string $message, string $file, int $line): bool
    {
        // Leave errors that would otherwise be silenced alone
        if (!($level & error_reporting())) {
            return false;
        }

        // Ignore explicitly silenced errors
        foreach ($this->Silenced as [$pattern, $levels]) {
            if (
                ($levels & $level)
                && Regex::match($pattern, $file)
            ) {
                return true;
            }
        }

        // Convert the error to an exception
        $exception = new ErrorException($message, 0, $level, $file, $line);

        if ($this->IsShuttingDown) {
            $this->handleException($exception);
            return true;
        }

        throw $exception;
    }

    /**
     * @internal
     */
    public function handleException(Throwable $exception): void
    {
        if ($this->IsShuttingDown) {
            Console::exception($exception);
            return;
        }

        $this->IsShuttingDown = true;
        $this->IsShuttingDownOnUncaughtException = true;
        if ($exception instanceof Exception) {
            $exitStatus = $exception->getExitStatus();
        }
        $this->ExitStatus = $exitStatus ??= self::DEFAULT_EXIT_STATUS;
        Console::exception($exception);
        exit($exitStatus);
    }

    /**
     * Report the exit status of the running script before it terminates on
     * SIGTERM, SIGINT or SIGHUP
     *
     * @throws LogicException if the instance is not registered to handle errors
     * and exceptions.
     */
    public function handleExitSignal(int $exitStatus): void
    {
        if (!$this->IsRegistered) {
            throw new LogicException(sprintf('%s is not registered', static::class));
        }

        if ($this->IsShuttingDown) {
            return;
        }

        $this->IsShuttingDown = true;
        $this->IsShuttingDownOnExitSignal = true;
        $this->ExitStatus = $exitStatus;
    }
}
