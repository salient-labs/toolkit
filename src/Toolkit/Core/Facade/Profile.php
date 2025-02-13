<?php declare(strict_types=1);

namespace Salient\Core\Facade;

use Salient\Core\MetricCollector;

/**
 * A facade for MetricCollector
 *
 * @method static int add(int $value, string $counter, string $group = 'general') Add a value to a counter and return its value
 * @method static int count(string $counter, string $group = 'general') Increment a counter and return its value
 * @method static int getCounter(string $counter, string $group = 'general') Get the value of a counter
 * @method static array<string,array<string,int>>|array<string,int> getCounters(string[]|string|null $groups = null) Get counter values (see {@see MetricCollector::getCounters()})
 * @method static array<string,int>|int getMaxTimers(string[]|string|null $groups = null) Get the maximum number of timers running simultaneously (see {@see MetricCollector::getMaxTimers()})
 * @method static array{float,int} getTimer(string $timer, string $group = 'general', bool $includeRunning = true) Get the start count and elapsed milliseconds of a timer
 * @method static array<string,array<string,array{float,int}>>|array<string,array{float,int}> getTimers(bool $includeRunning = true, string[]|string|null $groups = null) Get timer start counts and elapsed milliseconds (see {@see MetricCollector::getTimers()})
 * @method static void pop() Pop metrics off the stack
 * @method static void push() Push the current state of all metrics onto the stack
 * @method static void startTimer(string $timer, string $group = 'general') Start a timer based on the system's high-resolution time
 * @method static float stopTimer(string $timer, string $group = 'general') Stop a timer and return the elapsed milliseconds
 *
 * @api
 *
 * @extends Facade<MetricCollector>
 *
 * @generated
 */
final class Profile extends Facade
{
    /**
     * @internal
     */
    protected static function getService()
    {
        return MetricCollector::class;
    }
}
