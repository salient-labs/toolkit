<?php declare(strict_types=1);

namespace Lkrms\Facade;

use Lkrms\Concept\Facade;
use Lkrms\Support\MetricCollector;

/**
 * A facade for \Lkrms\Support\MetricCollector
 *
 * @method static MetricCollector load() Load and return an instance of the underlying MetricCollector class
 * @method static MetricCollector getInstance() Get the underlying MetricCollector instance
 * @method static bool isLoaded() True if an underlying MetricCollector instance has been loaded
 * @method static void unload() Clear the underlying MetricCollector instance
 * @method static int<1,max> count(string $counter, string $group = 'general') Increment a counter and return its value
 * @method static int getCounter(string $counter, string $group = 'general') Get the value of a counter
 * @method static array<string,array<string,int>>|array<string,int> getCounters(string[]|string|null $groups = null) Get counter values (see {@see MetricCollector::getCounters()})
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
     * @inheritDoc
     */
    protected static function getServiceName(): string
    {
        return MetricCollector::class;
    }
}
