<?php declare(strict_types=1);

namespace Salient\Core\Utility;

use Salient\Core\Exception\InvalidEnvironmentException;
use Salient\Core\Facade\Console;
use Salient\Core\AbstractUtility;
use LogicException;
use SQLite3;

/**
 * Get information about the runtime environment
 */
final class Sys extends AbstractUtility
{
    /**
     * Get the configured memory_limit, in bytes
     */
    public static function getMemoryLimit(): int
    {
        return Get::bytes((string) ini_get('memory_limit'));
    }

    /**
     * Get the current memory usage of the script, in bytes
     */
    public static function getMemoryUsage(): int
    {
        return memory_get_usage();
    }

    /**
     * Get the peak memory usage of the script, in bytes
     */
    public static function getPeakMemoryUsage(): int
    {
        return memory_get_peak_usage();
    }

    /**
     * Get the current memory usage of the script as a percentage of the
     * memory_limit
     */
    public static function getMemoryUsagePercent(): int
    {
        $limit = self::getMemoryLimit();

        return $limit <= 0
            ? 0
            : (int) round(memory_get_usage() * 100 / $limit);
    }

    /**
     * Get user and system CPU times for the current run, in microseconds
     *
     * @return array{int,int} User CPU time is at index 0 and is followed by
     * system CPU time.
     */
    public static function getCpuUsage(): array
    {
        $usage = getrusage();

        if ($usage === false) {
            return [0, 0];
        }

        $user_s = $usage['ru_utime.tv_sec'] ?? 0;
        $user_us = $usage['ru_utime.tv_usec'] ?? 0;
        $sys_s = $usage['ru_stime.tv_sec'] ?? 0;
        $sys_us = $usage['ru_stime.tv_usec'] ?? 0;

        return [
            $user_s * 1000000 + $user_us,
            $sys_s * 1000000 + $sys_us,
        ];
    }

    /**
     * Get the filename used to run the script
     *
     * To get the running script's canonical path relative to the application,
     * set `$basePath` to the application's root directory.
     *
     * @throws LogicException if the filename used to run the script doesn't
     * belong to `$basePath`.
     */
    public static function getProgramName(?string $basePath = null): string
    {
        $filename = $_SERVER['SCRIPT_FILENAME'];

        if ($basePath === null) {
            return $filename;
        }

        $filename = File::relativeToParent($filename, $basePath);
        if ($filename === null) {
            throw new LogicException(sprintf(
                "'%s' is not in '%s'",
                $_SERVER['SCRIPT_FILENAME'],
                $basePath,
            ));
        }
        return $filename;
    }

    /**
     * Get the basename of the file used to run the script
     *
     * @param string ...$suffixes Removed from the end of the filename.
     */
    public static function getProgramBasename(string ...$suffixes): string
    {
        $basename = basename($_SERVER['SCRIPT_FILENAME']);

        if (!$suffixes) {
            return $basename;
        }

        foreach ($suffixes as $suffix) {
            $length = strlen($suffix);
            if (substr($basename, -$length) === $suffix) {
                return substr($basename, 0, -$length);
            }
        }

        return $basename;
    }

    /**
     * Get the user ID or username of the current user
     *
     * @return int|string
     */
    public static function getUserId()
    {
        if (function_exists('posix_geteuid')) {
            return posix_geteuid();
        }

        $user = Env::getNullable('USERNAME', null);
        if ($user !== null) {
            return $user;
        }

        $user = Env::getNullable('USER', null);
        if ($user === null) {
            throw new InvalidEnvironmentException('Unable to identify user');
        }
        return $user;
    }

    /**
     * Get a command string with arguments escaped for this platform's shell
     *
     * Don't use this method to prepare commands for {@see proc_open()}. Its
     * quoting behaviour on Windows is unstable.
     *
     * @param string[] $args
     */
    public static function escapeCommand(array $args): string
    {
        $command = '';

        if (!self::isWindows()) {
            foreach ($args as $arg) {
                $command .= ($command ? ' ' : '') . self::escapeShellArg($arg);
            }

            return $command;
        }

        foreach ($args as $arg) {
            $command .= ($command ? ' ' : '') . self::escapeCmdArg($arg);
        }

        return $command;
    }

    /**
     * Escape an argument for POSIX-compatible shells
     */
    private static function escapeShellArg(string $arg): string
    {
        if ($arg === '' || Pcre::match('/[^a-z0-9+.\/@_-]/i', $arg)) {
            return "'" . str_replace("'", "'\''", $arg) . "'";
        }

        return $arg;
    }

    /**
     * Escape an argument for cmd.exe on Windows
     *
     * Derived from `Composer\Util\ProcessExecutor::escapeArgument()`, which
     * credits <https://github.com/johnstevenson/winbox-args>.
     */
    private static function escapeCmdArg(string $arg): string
    {
        $arg = Pcre::replace('/(\\\\*)"/', '$1$1\"', $arg, -1, $quoteCount);
        $quote = $arg === '' || strpbrk($arg, " \t,") !== false;
        $meta = $quoteCount > 0 || Pcre::match('/%[^%]+%|![^!]+!/', $arg);

        if (!$meta && !$quote) {
            $quote = strpbrk($arg, '^&|<>()') !== false;
        }

        if ($quote) {
            $arg = '"' . Pcre::replace('/(\\\\*)$/', '$1$1', $arg) . '"';
        }

        if ($meta) {
            $arg = Pcre::replace('/["^&|<>()%!]/', '^$0', $arg);
        }

        return $arg;
    }

    /**
     * True if the script is running on Windows
     */
    public static function isWindows(): bool
    {
        return \PHP_OS_FAMILY === 'Windows';
    }

    /**
     * True if the SQLite3 library supports UPSERT syntax
     *
     * @link https://www.sqlite.org/lang_UPSERT.html
     */
    public static function sqliteHasUpsert(): bool
    {
        return SQLite3::version()['versionNumber'] >= 3024000;
    }

    /**
     * Handle SIGINT and SIGTERM to make a clean exit from the running script
     *
     * If {@see posix_getpgid()} is available, `SIGINT` is propagated to the
     * current process group just before PHP exits.
     *
     * @return bool `false` if signal handlers can't be installed on this
     * platform, otherwise `true`.
     */
    public static function handleExitSignals(): bool
    {
        if (!function_exists('pcntl_async_signals')) {
            return false;
        }

        $handler =
            function (int $signal): void {
                Console::debug(sprintf('Received signal %d', $signal));
                if ($signal === \SIGINT &&
                        function_exists('posix_getpgid') &&
                        ($pgid = posix_getpgid(posix_getpid())) !== false) {
                    // Stop handling SIGINT before propagating it
                    pcntl_signal(\SIGINT, \SIG_DFL);
                    register_shutdown_function(
                        function () use ($pgid) {
                            Console::debug(sprintf('Sending SIGINT to process group %d', $pgid));
                            posix_kill($pgid, \SIGINT);
                        }
                    );
                }
                exit(64 + $signal);
            };

        pcntl_async_signals(true);
        pcntl_signal(\SIGINT, $handler);
        pcntl_signal(\SIGTERM, $handler);
        return true;
    }
}
