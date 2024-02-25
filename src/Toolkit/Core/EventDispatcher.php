<?php declare(strict_types=1);

namespace Salient\Core;

use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\EventDispatcher\ListenerProviderInterface;
use Psr\EventDispatcher\StoppableEventInterface;
use Salient\Core\Contract\Nameable;
use Salient\Core\Utility\Reflect;
use Salient\Core\Utility\Str;
use Generator;
use LogicException;

/**
 * Dispatches events to listeners
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
     * @var array<string,array<int,callable(object): mixed>>
     */
    private array $EventListeners = [];

    private int $NextListenerId = 0;

    /**
     * @var ListenerProviderInterface[]
     */
    private array $ListenerProviders = [];

    /**
     * Creates a new EventDispatcher object
     */
    public function __construct(?ListenerProviderInterface $listenerProvider = null)
    {
        if ($listenerProvider) {
            $this->ListenerProviders[] = $listenerProvider;
        }
        $this->ListenerProviders[] = $this;
    }

    /**
     * Register an event listener with the dispatcher
     *
     * Returns a listener ID that can be passed to
     * {@see EventDispatcher::removeListener()}.
     *
     * @param callable(object): mixed $listener
     * @param string[]|string|null $event An event or array of events. If
     * `null`, the listener is registered to receive events accepted by its
     * first parameter.
     */
    public function listen(callable $listener, $event = null): int
    {
        if ($event === null) {
            $event = Reflect::getFirstCallbackParameterClassNames($listener);
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
     * @template T of object
     *
     * @param T $event
     * @return T
     */
    public function dispatch(object $event): object
    {
        foreach ($this->ListenerProviders as $provider) {
            $listeners = $provider->getListenersForEvent($event);
            foreach ($listeners as $listener) {
                if ($event instanceof StoppableEventInterface &&
                        $event->isPropagationStopped()) {
                    return $event;
                }
                $listener($event);
            }
        }

        return $event;
    }

    /**
     * @return Generator<callable(object): mixed>
     */
    public function getListenersForEvent(object $event): Generator
    {
        $events = array_merge(
            [get_class($event)],
            class_parents($event),
            class_implements($event),
        );

        if ($event instanceof Nameable) {
            $eventName = $event->name();
            // If the event returns a name we already have, do nothing
            if (!is_a($event, $eventName)) {
                $events[] = $eventName;
            }
        }

        $index = [];
        foreach ($events as $event) {
            $index[Str::lower($event)] = null;
        }

        $listenersByEvent = array_intersect_key($this->EventListeners, $index);
        $listeners = [];
        foreach ($listenersByEvent as $eventListeners) {
            $listeners += $eventListeners;
        }

        foreach ($listeners as $listener) {
            yield $listener;
        }
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
