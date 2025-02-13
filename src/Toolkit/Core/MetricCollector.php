<?php declare(strict_types=1);

namespace Salient\Core;

use Salient\Contract\Core\Instantiable;
use LogicException;

/**
 * @api
 */
final class MetricCollector implements Instantiable
{
    private const COUNTER = 0;
    private const TIMER = 1;

    private const METRIC_NAME = [
        self::COUNTER => 'counter',
        self::TIMER => 'timer',
    ];

    /**
     * Group => name => metric
     *
     * @var array<string,array<string,int>>
     */
    private array $Metrics = [];

    /**
     * Group => name => count
     *
     * @var array<string,array<string,int>>
     */
    private array $Counters = [];

    /**
     * Group => name => start count
     *
     * @var array<string,array<string,int>>
     */
    private array $TimerRuns = [];

    /**
     * Group => name => start nanoseconds
     *
     * @var array<string,array<string,int|float>>
     */
    private array $RunningTimers = [];

    /**
     * Group => name => elapsed nanoseconds
     *
     * @var array<string,array<string,int|float>>
     */
    private array $ElapsedTime = [];

    /**
     * Group => maximum number of timers running simultaneously
     *
     * @var array<string,int>
     */
    private array $ConcurrentTimers = [];

    /** @var array<array{array<string,array<string,int>>,array<string,array<string,int>>,array<string,array<string,int>>,array<string,array<string,int|float>>,array<string,array<string,int|float>>,array<string,int>}> */
    private array $Stack = [];

    /**
     * @api
     */
    public function __construct() {}

    /**
     * Increment a counter and return its value
     */
    public function count(string $counter, string $group = 'general'): int
    {
        $this->assertMetricIs($counter, $group, self::COUNTER);
        $this->Counters[$group][$counter] ??= 0;
        return ++$this->Counters[$group][$counter];
    }

    /**
     * Add a value to a counter and return its value
     */
    public function add(int $value, string $counter, string $group = 'general'): int
    {
        $this->assertMetricIs($counter, $group, self::COUNTER);
        $this->Counters[$group][$counter] ??= 0;
        return $this->Counters[$group][$counter] += $value;
    }

    /**
     * Start a timer based on the system's high-resolution time
     */
    public function startTimer(string $timer, string $group = 'general'): void
    {
        $now = hrtime(true);
        $this->assertMetricIs($timer, $group, self::TIMER);
        if (isset($this->RunningTimers[$group][$timer])) {
            throw new LogicException(sprintf('Timer already running: %s', $timer));
        }
        $this->RunningTimers[$group][$timer] = $now;
        $this->TimerRuns[$group][$timer] ??= 0;
        $this->TimerRuns[$group][$timer]++;
        $count = count($this->RunningTimers[$group]);
        $this->ConcurrentTimers[$group] ??= $count;
        $this->ConcurrentTimers[$group] = max($this->ConcurrentTimers[$group], $count);
    }

    /**
     * Stop a timer and return the elapsed milliseconds
     */
    public function stopTimer(string $timer, string $group = 'general'): float
    {
        $now = hrtime(true);
        if (!isset($this->RunningTimers[$group][$timer])) {
            throw new LogicException(sprintf('Timer not running: %s', $timer));
        }
        $elapsed = $now - $this->RunningTimers[$group][$timer];
        unset($this->RunningTimers[$group][$timer]);
        $this->ElapsedTime[$group][$timer] ??= 0;
        $this->ElapsedTime[$group][$timer] += $elapsed;

        return (float) $elapsed / 1000000;
    }

    /**
     * Push the current state of all metrics onto the stack
     */
    public function push(): void
    {
        $this->Stack[] = [
            $this->Metrics,
            $this->Counters,
            $this->TimerRuns,
            $this->RunningTimers,
            $this->ElapsedTime,
            $this->ConcurrentTimers,
        ];
    }

    /**
     * Pop metrics off the stack
     */
    public function pop(): void
    {
        $metrics = array_pop($this->Stack);

        if (!$metrics) {
            throw new LogicException('Empty stack');
        }

        [
            $this->Metrics,
            $this->Counters,
            $this->TimerRuns,
            $this->RunningTimers,
            $this->ElapsedTime,
            $this->ConcurrentTimers,
        ] = $metrics;
    }

    /**
     * Get the value of a counter
     */
    public function getCounter(string $counter, string $group = 'general'): int
    {
        return $this->Counters[$group][$counter] ?? 0;
    }

    /**
     * Get counter values
     *
     * Returns an array that maps groups to counters:
     *
     * ```
     * [ <group> => [ <counter> => <value>, ... ], ... ]
     * ```
     *
     * Or, if `$groups` is a string:
     *
     * ```
     * [ <counter> => <value>, ... ]
     * ```
     *
     * @param string[]|string|null $groups If `null` or `["*"]`, all counters
     * are returned, otherwise only counters in the given groups are returned.
     * @return array<string,array<string,int>>|array<string,int>
     * @phpstan-return ($groups is string ? array<string,int> : array<string,array<string,int>>)
     */
    public function getCounters($groups = null): array
    {
        if ($groups === null || $groups === ['*']) {
            return $this->Counters;
        } elseif (is_string($groups)) {
            return $this->Counters[$groups] ?? [];
        } else {
            return array_intersect_key($this->Counters, array_flip($groups));
        }
    }

    /**
     * Get the start count and elapsed milliseconds of a timer
     *
     * @return array{float,int}
     */
    public function getTimer(
        string $timer,
        string $group = 'general',
        bool $includeRunning = true
    ): array {
        return $this->doGetTimer($timer, $group, $includeRunning) ?? [0.0, 0];
    }

    /**
     * Get timer start counts and elapsed milliseconds
     *
     * Returns an array that maps groups to timers:
     *
     * ```
     * [ <group> => [ <timer> => [ <elapsed_ms>, <start_count> ], ... ], ... ]
     * ```
     *
     * Or, if `$groups` is a string:
     *
     * ```
     * [ <timer> => [ <elapsed_ms>, <start_count> ], ... ]
     * ```
     *
     * @param string[]|string|null $groups If `null` or `["*"]`, all timers are
     * returned, otherwise only timers in the given groups are returned.
     * @return array<string,array<string,array{float,int}>>|array<string,array{float,int}>
     * @phpstan-return ($groups is string ? array<string,array{float,int}> : array<string,array<string,array{float,int}>>)
     */
    public function getTimers(bool $includeRunning = true, $groups = null): array
    {
        if ($groups === null || $groups === ['*']) {
            $timerRuns = $this->TimerRuns;
        } else {
            $timerRuns = array_intersect_key($this->TimerRuns, array_flip((array) $groups));
        }

        foreach ($timerRuns as $group => $runs) {
            foreach ($runs as $name => $count) {
                $timer = $this->doGetTimer($name, $group, $includeRunning, $count, $now);
                if ($timer !== null) {
                    $timers[$group][$name] = $timer;
                }
            }
        }

        return is_string($groups)
            ? $timers[$groups] ?? []
            : $timers ?? [];
    }

    /**
     * Get the maximum number of timers running simultaneously
     *
     * @param string[]|string|null $groups If `null` or `["*"]`, values are
     * returned for all groups, otherwise only values for the given groups are
     * returned.
     * @return array<string,int>|int
     * @phpstan-return ($groups is string ? int : array<string,int>)
     */
    public function getMaxTimers($groups = null)
    {
        if ($groups === null || $groups === ['*']) {
            return $this->ConcurrentTimers;
        } elseif (is_string($groups)) {
            return $this->ConcurrentTimers[$groups] ?? 0;
        } else {
            return array_intersect_key($this->ConcurrentTimers, array_flip($groups));
        }
    }

    /**
     * @param int|float|null $now
     * @return array{float,int}|null
     */
    private function doGetTimer(
        string $timer,
        string $group,
        bool $includeRunning,
        ?int $count = null,
        &$now = null
    ): ?array {
        $count ??= $this->TimerRuns[$group][$timer] ?? null;
        if ($count === null) {
            return null;
        }
        $elapsed = $this->ElapsedTime[$group][$timer] ?? 0;
        if ($includeRunning && isset($this->RunningTimers[$group][$timer])) {
            // Passed by reference so `hrtime()` is called once per call to
            // `getTimers()` and only if necessary
            $now ??= hrtime(true);
            $elapsed += $now - $this->RunningTimers[$group][$timer];
        }
        if (!$elapsed) {
            return null;
        }
        return [(float) $elapsed / 1000000, $count];
    }

    /**
     * @param self::COUNTER|self::TIMER $metric
     */
    private function assertMetricIs(string $name, string $group, int $metric): void
    {
        $this->Metrics[$group][$name] ??= $metric;
        if ($this->Metrics[$group][$name] !== $metric) {
            throw new LogicException(sprintf(
                'Not a %s: %s (group=%s)',
                self::METRIC_NAME[$metric],
                $name,
                $group,
            ));
        }
    }
}
