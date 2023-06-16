<?php declare(strict_types=1);

namespace Lkrms\Support;

use LogicException;

/**
 * Dispatches events to listeners
 *
 */
final class EventDispatcher
{
    /**
     * Listener ID => list of events
     *
     * @var array<int,string[]>
     */
    private $Listeners = [];

    /**
     * Event => [ listener ID => listener ]
     *
     * @var array<string,array<int,callable(mixed, string): (bool|void)>>
     */
    private $EventListeners = [];

    /**
     * @var int
     */
    private $NextListenerId = 0;

    /**
     * Register an event listener with the dispatcher
     *
     * Returns a listener ID that can be passed to
     * {@see EventDispatcher::removeListener()}.
     *
     * @param string|string[] $event An event or array of events.
     * @param callable(mixed, string): (bool|void) $listener Receives the event
     * payload and the name of the dispatched event.
     */
    public function listen($event, callable $listener): int
    {
        $id = $this->NextListenerId++;
        foreach ((array) $event as $event) {
            $this->Listeners[$id][] = $event;
            $this->EventListeners[$event][$id] = $listener;
        }

        return $id;
    }

    /**
     * Dispatch an event to listeners registered to receive it
     *
     * If `$cancellable` is `true` and a listener returns `false`, no further
     * listeners will receive the event.
     *
     * @param mixed $payload
     * @return mixed[]|false Returns an array of listener responses, or `false`
     * if the event was cancellable and a listener returned `false`.
     */
    public function dispatch(string $event, $payload = null, bool $cancellable = false)
    {
        foreach ($this->EventListeners[$event] ?? [] as $listener) {
            $responses[] = $response = $listener($payload, $event);
            if ($cancellable && $response === false) {
                return false;
            }
        }
        return $responses ?? [];
    }

    /**
     * Remove an event listener from the dispatcher
     *
     * @param int $id A listener ID returned by
     * {@see EventDispatcher::listen()}.
     */
    public function removeListener(int $id): void
    {
        if (!array_key_exists($id, $this->Listeners)) {
            throw new LogicException('No listener with that ID');
        }
        foreach ($this->Listeners[$id] as $event) {
            unset($this->EventListeners[$event][$id]);
            if (!$this->EventListeners[$event]) {
                unset($this->EventListeners[$event]);
            }
        }
        unset($this->Listeners[$id]);
    }
}
