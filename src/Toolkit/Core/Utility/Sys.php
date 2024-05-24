<?php declare(strict_types=1);

namespace Salient\Core\Utility;

use Salient\Core\Exception\InvalidEnvironmentException;
use Salient\Core\Exception\LogicException;
use Salient\Core\Exception\RuntimeException;
use Salient\Core\AbstractUtility;

/**
 * Get information about the runtime environment
 *
 * @api
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
    public static function getMemoryUsagePercent(): float
    {
        $limit = self::getMemoryLimit();

        return $limit <= 0
            ? 0
            : (memory_get_usage() * 100 / $limit);
    }

    /**
     * Get user and system CPU times for the current run, in microseconds
     *
     * @return array{int,int} `[<user_time>, <system_time>]`
     */
    public static function getCpuUsage(): array
    {
        $usage = getrusage();

        if ($usage === false) {
            // @codeCoverageIgnoreStart
            return [0, 0];
            // @codeCoverageIgnoreEnd
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
     * Use `$parentDir` to get the running script's path relative to a parent
     * directory.
     *
     * @throws LogicException if the running script is not in `$parentDir`.
     */
    public static function getProgramName(?string $parentDir = null): string
    {
        $filename = $_SERVER['SCRIPT_FILENAME'];

        if ($parentDir === null) {
            return $filename;
        }

        $filename = File::relativeToParent($filename, $parentDir);
        if ($filename === null) {
            throw new LogicException(sprintf(
                "'%s' is not in '%s'",
                $_SERVER['SCRIPT_FILENAME'],
                $parentDir,
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

        // @codeCoverageIgnoreStart
        $user = Env::getNullable('USER', null);
        if ($user !== null) {
            return $user;
        }

        throw new InvalidEnvironmentException('Unable to identify user');
        // @codeCoverageIgnoreEnd
    }

    /**
     * Check if a process with the given process ID is running
     */
    public static function isProcessRunning(int $pid): bool
    {
        if (!self::isWindows()) {
            return posix_kill($pid, 0);
        }

        $command = sprintf('tasklist /fo csv /nh /fi "PID eq %d"', $pid);
        $stream = File::openPipe($command, 'r');
        $csv = File::getCsv($stream);
        if (File::closePipe($stream, $command) !== 0) {
            // @codeCoverageIgnoreStart
            throw new RuntimeException(
                sprintf('Command failed: %s', $command)
            );
            // @codeCoverageIgnoreEnd
        }

        return count($csv) === 1
            && isset($csv[0][1])
            && $csv[0][1] === (string) $pid;
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
        $windows = self::isWindows();

        foreach ($args as &$arg) {
            $arg = $windows
                ? self::escapeCmdArg($arg)
                : self::escapeShellArg($arg);
        }

        return implode(' ', $args);
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
     * Make a clean exit from the running script on SIGTERM, SIGINT or SIGHUP
     *
     * @return bool `false` if signal handlers can't be installed on this
     * platform, otherwise `true`.
     */
    public static function handleExitSignals(): bool
    {
        if (!function_exists('pcntl_async_signals')) {
            return false;
        }
        $handler = static function (int $signal): void {
            // @codeCoverageIgnoreStart
            exit(128 + $signal);
            // @codeCoverageIgnoreEnd
        };
        pcntl_async_signals(true);
        return pcntl_signal(\SIGTERM, $handler)
            && pcntl_signal(\SIGINT, $handler)
            && pcntl_signal(\SIGHUP, $handler);
    }
}
