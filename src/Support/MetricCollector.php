<?php declare(strict_types=1);

namespace Lkrms\Support;

use LogicException;

/**
 * Uses the system's high-resolution time for simple profiling
 */
final class Timekeeper
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
     * @var array<array{array<string,array<string,int>>,array<string,array<string,int|float>>,array<string,array<string,int|float>>}>
     */
    private $TimerStack = [];

    /**
     * Start a timer using the system's high-resolution time
     *
     * @api
     */
    public function startTimer(string $name, string $type = 'general'): void
    {
        $now = hrtime(true);
        if (array_key_exists($name, $this->RunningTimers[$type] ?? [])) {
            throw new LogicException(sprintf('Timer already running: %s', $name));
        }
        $this->RunningTimers[$type][$name] = $now;
        $this->TimerRuns[$type][$name] = ($this->TimerRuns[$type][$name] ?? 0) + 1;
    }

    /**
     * Stop a timer and return the elapsed milliseconds
     *
     * Elapsed time is also added to the totals returned by
     * {@see Timekeeper::getTimers()}.
     *
     * @api
     */
    public function stopTimer(string $name, string $type = 'general'): float
    {
        $now = hrtime(true);
        if (!array_key_exists($name, $this->RunningTimers[$type] ?? [])) {
            throw new LogicException(sprintf('Timer not running: %s', $name));
        }
        $elapsed = $now - $this->RunningTimers[$type][$name];
        unset($this->RunningTimers[$type][$name]);
        $this->ElapsedTime[$type][$name] = ($this->ElapsedTime[$type][$name] ?? 0) + $elapsed;

        return $elapsed / 1000000;
    }

    /**
     * Push timer state onto the stack
     *
     * @api
     */
    public function pushTimers(): void
    {
        $this->TimerStack[] =
            [$this->TimerRuns, $this->RunningTimers, $this->ElapsedTime];
    }

    /**
     * Pop timer state off the stack
     *
     * @api
     */
    public function popTimers(): void
    {
        $timers = array_pop($this->TimerStack);
        if (!$timers) {
            throw new LogicException('No timer state to pop off the stack');
        }

        [$this->TimerRuns, $this->RunningTimers, $this->ElapsedTime] =
            $timers;
    }

    /**
     * Get elapsed milliseconds and start counts for timers started in the
     * current run
     *
     * @api
     *
     * @param string[]|string|null $types If `null` or `["*"]`, all timers are
     * returned, otherwise only timers of the given types are returned.
     * @return array<string,array<string,array{float,int}>> An array that maps
     * timer types to `<timer_name> => [ <elapsed_ms>, <start_count> ]` arrays.
     */
    public function getTimers(bool $includeRunning = true, $types = null): array
    {
        if ($types === null || $types === ['*']) {
            $timerRuns = $this->TimerRuns;
        } else {
            $timerRuns = array_intersect_key($this->TimerRuns, array_flip((array) $types));
        }

        foreach ($timerRuns as $type => $runs) {
            foreach ($runs as $name => $count) {
                $elapsed = $this->ElapsedTime[$type][$name] ?? 0;
                if ($includeRunning &&
                        array_key_exists($name, $this->RunningTimers[$type] ?? [])) {
                    $elapsed += ($now ?? ($now = hrtime(true))) - $this->RunningTimers[$type][$name];
                }
                if (!$elapsed) {
                    continue;
                }
                $timers[$type][$name] = [$elapsed / 1000000, $count];
            }
        }

        return $timers ?? [];
    }
}
