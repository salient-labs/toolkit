<?php declare(strict_types=1);

namespace Salient\Tests\Core;

use Lkrms\Contract\HasName;
use Salient\Core\Facade\Event;
use Salient\Core\EventDispatcher;
use Salient\Tests\Core\EventDispatcher\BaseEvent;
use Salient\Tests\Core\EventDispatcher\LoggableEvent;
use Salient\Tests\Core\EventDispatcher\MainEvent;
use Salient\Tests\Core\EventDispatcher\NamedEvent;
use LogicException;

final class EventDispatcherTest extends \PHPUnit\Framework\TestCase
{
    public function testDispatch(): void
    {
        $logBaseEvent = [];
        $logMainEvent = [];
        $logNamedEvent = [];
        $logWithName = [];
        $logLoggableEvent = [];

        $logger =
            function (object $event, array &$log): void {
                if ($event instanceof HasName) {
                    $log[] = [$event->name() => get_class($event)];
                    return;
                }
                $log[] = get_class($event);
            };

        $dispatcher = new EventDispatcher();
        $dispatcher->listen(function (BaseEvent $event) use (&$logBaseEvent, $logger) {
            $logger($event, $logBaseEvent);
        });
        $dispatcher->listen(function (MainEvent $event) use (&$logMainEvent, $logger) {
            $logger($event, $logMainEvent);
        });
        $dispatcher->listen(function (NamedEvent $event) use (&$logNamedEvent, $logger) {
            $logger($event, $logNamedEvent);
        });
        $dispatcher->listen(function (object $event) use (&$logWithName, $logger) {
            $logger($event, $logWithName);
        }, ['onLoad', 'onSave']);
        $dispatcher->listen([$this, 'listenCallback']);

        $dispatcher->dispatch(new BaseEvent());
        $dispatcher->dispatch(new MainEvent());
        $dispatcher->dispatch(new NamedEvent(__METHOD__));
        $dispatcher->dispatch(new NamedEvent('onLoad'));
        $dispatcher->dispatch(new NamedEvent('onLogout'));
        $dispatcher->dispatch(new LoggableEvent($logger, $logLoggableEvent));

        $this->assertSame([
            BaseEvent::class,
            MainEvent::class,
            [__METHOD__ => NamedEvent::class],
            ['onLoad' => NamedEvent::class],
            ['onLogout' => NamedEvent::class],
            LoggableEvent::class,
        ], $logBaseEvent);

        $this->assertSame([
            MainEvent::class,
        ], $logMainEvent);

        $this->assertSame([
            ['onLoad' => NamedEvent::class],
        ], $logWithName);

        $this->assertSame([
            LoggableEvent::class,
        ], $logLoggableEvent);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('At least one event must be given');
        $dispatcher->listen(fn(object $event) => $event);
    }

    public function testFacade(): void
    {
        $received = 0;
        Event::listen(
            function (MainEvent $event) use (&$received) {
                $received++;
            }
        );
        Event::dispatch(new MainEvent());
        $this->assertSame(1, $received);
    }

    public function listenCallback(LoggableEvent $event): void
    {
        $event->log();
    }

    public function testListenerWithNoParameters(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('$callable has no parameters');
        $dispatcher = new EventDispatcher();
        $dispatcher->listen(fn() => null);
    }

    protected function tearDown(): void
    {
        if (Event::isLoaded()) {
            Event::unload();
        }
    }
}
