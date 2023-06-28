<?php declare(strict_types=1);

namespace Lkrms\Facade;

use Lkrms\Concept\Facade;
use Lkrms\Support\EventDispatcher;

/**
 * A facade for \Lkrms\Support\EventDispatcher
 *
 * @method static EventDispatcher load() Load and return an instance of the underlying EventDispatcher class
 * @method static EventDispatcher getInstance() Get the underlying EventDispatcher instance
 * @method static bool isLoaded() True if an underlying EventDispatcher instance has been loaded
 * @method static void unload() Clear the underlying EventDispatcher instance
 * @method static mixed[]|false dispatch(string $event, mixed $payload = null, bool $cancellable = false) Dispatch an event to listeners registered to receive it (see {@see EventDispatcher::dispatch()})
 * @method static int listen(string|string[] $event, callable(mixed, string): (bool|void) $listener) Register an event listener with the dispatcher (see {@see EventDispatcher::listen()})
 * @method static void removeListener(int $id) Remove an event listener from the dispatcher (see {@see EventDispatcher::removeListener()})
 *
 * @uses EventDispatcher
 *
 * @extends Facade<EventDispatcher>
 */
final class Event extends Facade
{
    /**
     * @internal
     */
    protected static function getServiceName(): string
    {
        return EventDispatcher::class;
    }
}
