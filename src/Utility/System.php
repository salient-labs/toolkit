<?php declare(strict_types=1);

namespace Lkrms\Utility;

use Lkrms\Facade\Console;
use Lkrms\Facade\Convert;
use Lkrms\Facade\File;
use RuntimeException;
use SQLite3;

/**
 * Get information about the runtime environment
 *
 */
final class System
{
    /**
     * Type => name => start count
     *
     * @var array<string,array<string,int>>
     */
    private $TimerRuns = [];

    /**
     * Type => name => start nanoseconds
     *
     * @var array<string,array<string,int|float>>
     */
    private $RunningTimers = [];

    /**
     * Type => name => elapsed nanoseconds
     *
     * @var array<string,array<string,int|float>>
     */
    private $ElapsedTime = [];

    /**
     * Get the configured memory_limit in bytes
     *
     */
    public function getMemoryLimit(): int
    {
        return Convert::sizeToBytes(ini_get('memory_limit') ?: '0');
    }

    /**
     * Get the current memory usage of the script in bytes
     *
     */
    public function getMemoryUsage(): int
    {
        return memory_get_usage();
    }

    /**
     * Get the peak memory usage of the script in bytes
     *
     */
    public function getPeakMemoryUsage(): int
    {
        return memory_get_peak_usage();
    }

    /**
     * Get the current memory usage of the script as a percentage of the
     * memory_limit
     *
     */
    public function getMemoryUsagePercent(): int
    {
        $limit = $this->getMemoryLimit();

        return $limit <= 0
            ? 0
            : (int) round(memory_get_usage() * 100 / $limit);
    }

    /**
     * Get user and system CPU times for the current run, in microseconds
     *
     * @return int[]
     * ```php
     * [$userMicroseconds, $systemMicroseconds]
     * ```
     */
    public function getCpuUsage(): array
    {
        if (($usage = getrusage()) === false) {
            return [
                0,
                0,
            ];
        }

        return [
            ($usage['ru_utime.tv_sec'] ?? 0) * 1000000 + ($usage['ru_utime.tv_usec'] ?? 0),
            ($usage['ru_stime.tv_sec'] ?? 0) * 1000000 + ($usage['ru_stime.tv_usec'] ?? 0),
        ];
    }

    /**
     * Start a timer using the system's high-resolution time
     *
     */
    public function startTimer(string $name, string $type = 'general'): void
    {
        $now = hrtime(true);
        if (array_key_exists($name, $this->RunningTimers[$type] ?? [])) {
            throw new RuntimeException(sprintf('Timer already running: %s', $name));
        }
        $this->RunningTimers[$type][$name] = $now;
        $this->TimerRuns[$type][$name]     = ($this->TimerRuns[$type][$name] ?? 0) + 1;
    }

    /**
     * Stop a timer and return the elapsed milliseconds
     *
     * The elapsed time is also added to the totals returned by
     * {@see System::getTimers()}.
     *
     */
    public function stopTimer(string $name, string $type = 'general'): float
    {
        $now = hrtime(true);
        if (!array_key_exists($name, $this->RunningTimers[$type] ?? [])) {
            throw new RuntimeException(sprintf('Timer not running: %s', $name));
        }
        $elapsed = $now - $this->RunningTimers[$type][$name];
        unset($this->RunningTimers[$type][$name]);
        $this->ElapsedTime[$type][$name] = ($this->ElapsedTime[$type][$name] ?? 0) + $elapsed;

        return $elapsed / 1000000;
    }

    /**
     * Get the elapsed milliseconds and start count for timers started in the
     * current run
     *
     * @return array<string,array<string,array{float,int}>> An array that maps
     * timer types to `<timer_name> => <milliseconds_elapsed>` arrays.
     */
    public function getTimers(bool $includeRunning = true, ?string $type = null): array
    {
        $timerRuns = is_null($type)
            ? $this->TimerRuns
            : array_intersect_key($this->TimerRuns, [$type => 0]);
        foreach ($timerRuns as $_type => $runs) {
            foreach ($runs as $name => $count) {
                $elapsed = $this->ElapsedTime[$_type][$name] ?? 0;
                if ($includeRunning &&
                        array_key_exists($name, $this->RunningTimers[$_type] ?? [])) {
                    $elapsed += ($now ?? ($now = hrtime(true))) - $this->RunningTimers[$_type][$name];
                }
                if (!$elapsed) {
                    continue;
                }
                $timers[$_type][$name] = [$elapsed / 1000000, $count];
            }
        }

        return $timers ?? [];
    }

    /**
     * Get the filename used to run the script
     *
     * To get the running script's canonical path relative to the application,
     * set `$basePath` to the application's root directory.
     *
     * @throws RuntimeException if the filename used to run the script doesn't
     * belong to `$basePath`.
     */
    public function getProgramName(?string $basePath = null): string
    {
        $filename = $_SERVER['SCRIPT_FILENAME'];
        if (is_null($basePath)) {
            return $filename;
        }
        if (($basePath = File::realpath($basePath)) !== false &&
                ($filename = File::realpath($filename)) !== false &&
                strpos($filename, $basePath) === 0) {
            return substr($filename, strlen($basePath) + 1)
                ?: basename($filename);
        }

        throw new RuntimeException('SCRIPT_FILENAME is not in $basePath');
    }

    /**
     * Return the basename of the file used to run the script
     *
     * @param string ...$suffixes Removed from the end of the filename.
     */
    public function getProgramBasename(string ...$suffixes): string
    {
        $basename = basename($_SERVER['SCRIPT_FILENAME']);
        if (!$suffixes) {
            return $basename;
        }
        $regex = implode('|', array_map(fn(string $s) => preg_quote($s, '/'), $suffixes));

        return preg_replace("/(?<=.)({$regex})+$/", '', $basename);
    }

    /**
     * Return true if the SQLite3 library supports UPSERT syntax
     *
     * @link https://www.sqlite.org/lang_UPSERT.html
     */
    public function sqliteHasUpsert(): bool
    {
        return $this->getSQLite3Version() >= 3024000;
    }

    private function getSQLite3Version(): int
    {
        return SQLite3::version()['versionNumber'];
    }

    /**
     * Handle SIGINT and SIGTERM to make a clean exit from the running script
     *
     * If `posix_getpgid()` is available, `SIGINT` is propagated to the current
     * process group just before PHP exits.
     *
     * @return bool `false` if signal handlers can't be installed on this
     * platform, otherwise `true`.
     */
    public function handleExitSignals(): bool
    {
        if (!function_exists('pcntl_async_signals')) {
            return false;
        }
        $handler =
            function (int $signal): void {
                Console::debug('Received signal ' . $signal);
                if ($signal === SIGINT &&
                        function_exists('posix_getpgid') &&
                        ($pgid = posix_getpgid(posix_getpid())) !== false) {
                    // Stop handling SIGINT before propagating it
                    pcntl_signal(SIGINT, SIG_DFL);
                    register_shutdown_function(
                        function () use ($pgid) {
                            Console::debug('Sending SIGINT to process group ' . $pgid);
                            posix_kill($pgid, SIGINT);
                        }
                    );
                }
                exit ($signal);
            };

        pcntl_async_signals(true);
        pcntl_signal(SIGINT, $handler);
        pcntl_signal(SIGTERM, $handler);

        return true;
    }
}
