<?php declare(strict_types=1);

namespace Lkrms\Support;

use Lkrms\Contract\IFacade;
use Lkrms\Contract\ReceivesFacade;
use Lkrms\Facade\Console;
use Lkrms\Facade\File;
use Lkrms\Utility\Pcre;
use ErrorException;
use Throwable;

/**
 * Handle errors and uncaught exceptions
 */
final class ErrorHandler implements ReceivesFacade
{
    private const FATAL_ERRORS = E_ERROR
        | E_PARSE
        | E_CORE_ERROR
        | E_CORE_WARNING
        | E_COMPILE_ERROR
        | E_COMPILE_WARNING;

    /**
     * [ [ Path regex, error levels ], ... ]
     *
     * @var array<array{string,int}>
     */
    private array $Silenced = [];

    private int $ExitStatus = 15;

    private bool $IsRegistered = false;

    private bool $ShutdownIsRegistered = false;

    private bool $IsShuttingDown = false;

    /**
     * @var class-string<IFacade<static>>|null
     */
    private ?string $Facade = null;

    /**
     * @inheritDoc
     */
    public function setFacade(string $name)
    {
        $this->Facade = $name;
        return $this;
    }

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
     *
     * @return bool
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
    public function deregister(bool $unloadFacade = true)
    {
        if ($this->IsRegistered) {
            restore_error_handler();
            restore_exception_handler();
        }

        if ($unloadFacade && $this->Facade) {
            [$this->Facade, 'unload']();
            $this->Facade = null;
        }

        return $this;
    }

    /**
     * Silence errors in a file or directory
     *
     * @return $this
     */
    public function silencePath(string $path, int $levels = E_STRICT | E_DEPRECATED | E_USER_DEPRECATED)
    {
        $path = File::realpath($path);

        // Ignore paths that don't exist
        if ($path === false) {
            return $this;
        }

        if (is_dir($path)) {
            $path .= '/';
        }
        $this->silencePattern('/^' . preg_quote($path, '/') . '/', $levels);
        return $this;
    }

    /**
     * Silence errors in paths that match a regular expression
     *
     * @return $this
     */
    public function silencePattern(string $pattern, int $levels = E_STRICT | E_DEPRECATED | E_USER_DEPRECATED)
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
            exit ($this->ExitStatus);
        }
    }
}
