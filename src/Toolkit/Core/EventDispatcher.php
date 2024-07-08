<?php declare(strict_types=1);

namespace Salient\Core;

use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\EventDispatcher\ListenerProviderInterface;
use Psr\EventDispatcher\StoppableEventInterface;
use Salient\Contract\Core\HasName;
use Salient\Utility\Reflect;
use Salient\Utility\Str;
use LogicException;

/**
 * Dispatches events to listeners
 *
 * Implements PSR-14 (Event Dispatcher) interfaces.
 *
 * @api
 */
final class EventDispatcher implements EventDispatcherInterface, ListenerProviderInterface
{
    /**
     * Listener ID => list of events
     *
     * @var array<int,string[]>
     */
    private array $Listeners = [];

    /**
     * Event => [ listener ID => listener ]
     *
     * @var array<string,array<int,callable>>
     */
    private array $EventListeners = [];

    private int $NextListenerId = 0;
    private ListenerProviderInterface $ListenerProvider;

    /**
     * Creates a new EventDispatcher object
     *
     * If a listener provider is given, calls to methods other than
     * {@see EventDispatcher::dispatch()} will fail with a
     * {@see LogicException}.
     */
    public function __construct(?ListenerProviderInterface $listenerProvider = null)
    {
        $this->ListenerProvider = $listenerProvider ?? $this;
    }

    /**
     * Register an event listener with the dispatcher
     *
     * Returns a listener ID that can be passed to
     * {@see EventDispatcher::removeListener()}.
     *
     * @template TEvent of object
     *
     * @param callable(TEvent): mixed $listener
     * @param string[]|string|null $event An event or array of events. If
     * `null`, the listener is registered to receive events accepted by its
     * first parameter.
     */
    public function listen(callable $listener, $event = null): int
    {
        $this->assertIsListenerProvider();

        if ($event === null) {
            $event = [];
            foreach (Reflect::getAcceptedTypes($listener, true) as $name) {
                if (is_string($name)) {
                    $event[] = $name;
                }
            }
        }

        if ($event === []) {
            throw new LogicException('At least one event must be given');
        }

        $id = $this->NextListenerId++;
        foreach ((array) $event as $event) {
            $event = Str::lower($event);
            $this->Listeners[$id][] = $event;
            $this->EventListeners[$event][$id] = $listener;
        }

        return $id;
    }

    /**
     * Dispatch an event to listeners registered to receive it
     *
     * @template TEvent of object
     *
     * @param TEvent $event
     * @return TEvent
     */
    public function dispatch(object $event): object
    {
        $listeners = $this->ListenerProvider->getListenersForEvent($event);

        foreach ($listeners as $listener) {
            if (
                $event instanceof StoppableEventInterface
                && $event->isPropagationStopped()
            ) {
                break;
            }

            $listener($event);
        }

        return $event;
    }

    /**
     * @template TEvent of object
     *
     * @param TEvent $event
     * @return array<callable(TEvent): mixed>
     */
    public function getListenersForEvent(object $event): array
    {
        $this->assertIsListenerProvider();

        $events = array_merge(
            [get_class($event)],
            class_parents($event),
            class_implements($event),
        );

        if ($event instanceof HasName) {
            $eventName = $event->getName();
            // If the event returns a name we already have, do nothing
            if (!is_a($event, $eventName)) {
                $events[] = $eventName;
            }
        }

        $listenersByEvent = array_intersect_key(
            $this->EventListeners,
            array_change_key_case(array_fill_keys($events, null)),
        );

        $listeners = [];
        foreach ($listenersByEvent as $eventListeners) {
            $listeners += $eventListeners;
        }

        return array_values($listeners);
    }

    /**
     * Remove an event listener from the dispatcher
     *
     * @param int $id A listener ID returned by
     * {@see EventDispatcher::listen()}.
     */
    public function removeListener(int $id): void
    {
        $this->assertIsListenerProvider();

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

    private function assertIsListenerProvider(): void
    {
        if ($this->ListenerProvider !== $this) {
            throw new LogicException('Not a listener provider');
        }
    }
}
