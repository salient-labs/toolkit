<?php declare(strict_types=1);

namespace Lkrms\Facade;

use Lkrms\Concept\Facade;
use Lkrms\Support\Timekeeper;

/**
 * A facade for \Lkrms\Support\Timekeeper
 *
 * @method static Timekeeper load() Load and return an instance of the underlying Timekeeper class
 * @method static Timekeeper getInstance() Get the underlying Timekeeper instance
 * @method static bool isLoaded() True if an underlying Timekeeper instance has been loaded
 * @method static void unload() Clear the underlying Timekeeper instance
 * @method static array<string,array<string,array{float,int}>> getTimers(bool $includeRunning = true, string[]|string|null $types = null) Get elapsed milliseconds and start counts for timers started in the current run (see {@see Timekeeper::getTimers()})
 * @method static void popTimers() Pop timer state off the stack (see {@see Timekeeper::popTimers()})
 * @method static void pushTimers() Push timer state onto the stack (see {@see Timekeeper::pushTimers()})
 * @method static void startTimer(string $name, string $type = 'general') Start a timer using the system's high-resolution time (see {@see Timekeeper::startTimer()})
 * @method static float stopTimer(string $name, string $type = 'general') Stop a timer and return the elapsed milliseconds (see {@see Timekeeper::stopTimer()})
 *
 * @extends Facade<Timekeeper>
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
        return Timekeeper::class;
    }
}
