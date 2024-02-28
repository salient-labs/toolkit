<?php declare(strict_types=1);

namespace Salient\Core;

use Salient\Contract\Core\ExceptionInterface;
use Salient\Contract\Core\FacadeAwareInterface;
use Salient\Contract\Core\FacadeInterface;
use Salient\Core\Concern\UnloadsFacades;
use Salient\Core\Facade\Console;
use Salient\Core\Utility\File;
use Salient\Core\Utility\Pcre;
use ErrorException;
use Throwable;

/**
 * Handle errors and uncaught exceptions
 *
 * @implements FacadeAwareInterface<FacadeInterface<self>>
 */
final class ErrorHandler implements FacadeAwareInterface
{
    /** @use UnloadsFacades<FacadeInterface<self>> */
    use UnloadsFacades;

    private const FATAL_ERRORS = \E_ERROR
        | \E_PARSE
        | \E_CORE_ERROR
        | \E_CORE_WARNING
        | \E_COMPILE_ERROR
        | \E_COMPILE_WARNING;

    /**
     * [ [ Path regex, error levels ], ... ]
     *
     * @var array<array{string,int}>
     */
    private array $Silenced = [];

    private int $ExitStatus = 16;

    private bool $IsRegistered = false;

    private bool $ShutdownIsRegistered = false;

    private bool $IsShuttingDown = false;

    /**
     * Register error, exception and shutdown handlers
     *
     * @return $this
     */
    public function register()
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
     * True if error, exception and shutdown handlers are registered
     */
    public function isRegistered(): bool
    {
        return $this->IsRegistered;
    }

    /**
     * Deregister previously registered error and exception handlers
     *
     * @return $this
     */
    public function deregister()
    {
        if ($this->IsRegistered) {
            restore_error_handler();
            restore_exception_handler();
        }

        $this->unloadFacades();

        return $this;
    }

    /**
     * Silence errors in a file or directory
     *
     * @return $this
     */
    public function silencePath(string $path, int $levels = \E_STRICT | \E_DEPRECATED | \E_USER_DEPRECATED)
    {
        // Ignore paths that don't exist
        if (!file_exists($path)) {
            return $this;
        }

        $path = File::realpath($path);
        $this->silencePattern(
            '@^' . preg_quote($path, '@') . (is_dir($path) ? '/' : '$') . '@',
            $levels
        );
        return $this;
    }

    /**
     * Silence errors in paths that match a regular expression
     *
     * @return $this
     */
    public function silencePattern(string $pattern, int $levels = \E_STRICT | \E_DEPRECATED | \E_USER_DEPRECATED)
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
        if (!$this->IsRegistered) {
            return;
        }

        $this->IsShuttingDown = true;

        $error = error_get_last();
        if ($error && ($error['type'] & self::FATAL_ERRORS)) {
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
            if (($levels & $level) &&
                    Pcre::match($pattern, $file)) {
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
        Console::exception($exception);

        if (!$this->IsShuttingDown) {
            if ($exception instanceof ExceptionInterface) {
                $exitStatus = $exception->getExitStatus();
                if ($exitStatus !== null) {
                    exit($exitStatus);
                }
            }
            exit($this->ExitStatus);
        }
    }
}
