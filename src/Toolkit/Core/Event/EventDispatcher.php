<?php declare(strict_types=1);

namespace Salient\Core\Event;

use Psr\EventDispatcher\ListenerProviderInterface as PsrListenerProviderInterface;
use Psr\EventDispatcher\StoppableEventInterface as PsrStoppableEventInterface;
use Salient\Contract\Core\Event\EventDispatcherInterface;
use Salient\Contract\Core\HasName;
use Salient\Utility\Reflect;
use Salient\Utility\Str;
use InvalidArgumentException;
use LogicException;

/**
 * @api
 */
final class EventDispatcher implements EventDispatcherInterface
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
    private PsrListenerProviderInterface $ListenerProvider;

    /**
     * @api
     */
    public function __construct(?PsrListenerProviderInterface $listenerProvider = null)
    {
        $this->ListenerProvider = $listenerProvider ?? $this;
    }

    /**
     * @inheritDoc
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
            throw new InvalidArgumentException('At least one event must be given');
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
     * @inheritDoc
     */
    public function dispatch(object $event): object
    {
        $listeners = $this->ListenerProvider->getListenersForEvent($event);

        foreach ($listeners as $listener) {
            if (
                $event instanceof PsrStoppableEventInterface
                && $event->isPropagationStopped()
            ) {
                break;
            }

            $listener($event);
        }

        return $event;
    }

    /**
     * @inheritDoc
     */
    public function getListenersForEvent(object $event): iterable
    {
        $this->assertIsListenerProvider();

        $events = array_merge(
            [get_class($event)],
            class_parents($event),
            class_implements($event),
        );

        if ($event instanceof HasName) {
            $events[] = $event->getName();
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
     * @inheritDoc
     */
    public function removeListener(int $id): void
    {
        $this->assertIsListenerProvider();

        if (!array_key_exists($id, $this->Listeners)) {
            throw new InvalidArgumentException('No listener with that ID');
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
