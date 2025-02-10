<?php declare(strict_types=1);

namespace Salient\Contract\Core\Event;

use Psr\EventDispatcher\ListenerProviderInterface as PsrListenerProviderInterface;

/**
 * @api
 */
interface EventListenerProviderInterface extends PsrListenerProviderInterface
{
    /**
     * Register a listener for a given event or list of events
     *
     * @template TEvent of object
     *
     * @param callable(TEvent): mixed $listener
     * @param string[]|string|null $event If `null`, the listener is registered
     * for events accepted by its first parameter.
     * @return int A listener ID accepted by {@see removeListener()}.
     */
    public function listen(callable $listener, $event = null): int;

    /**
     * Deregister an event listener with a given listener ID
     *
     * @param int $id Returned by {@see listen()}.
     */
    public function removeListener(int $id): void;

    /**
     * Get listeners registered for a given event
     *
     * @template TEvent of object
     *
     * @param TEvent $event
     * @return iterable<callable(TEvent): mixed>
     */
    public function getListenersForEvent(object $event): iterable;
}
