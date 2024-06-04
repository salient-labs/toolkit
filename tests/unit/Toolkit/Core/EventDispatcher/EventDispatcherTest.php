<?php declare(strict_types=1);

namespace Salient\Tests\Core\EventDispatcher;

use Salient\Contract\Core\Nameable;
use Salient\Core\Facade\Event;
use Salient\Core\EventDispatcher;
use Salient\Tests\TestCase;
use LogicException;

/**
 * @covers \Salient\Core\EventDispatcher
 * @covers \Salient\Core\Facade\Event
 */
final class EventDispatcherTest extends TestCase
{
    public function testDispatch(): void
    {
        $logBaseEvent = [];
        $logMainEvent = [];
        $logNamedEvent = [];
        $logWithName = [];
        $logLoggableEvent = [];
        $logNamedLoggableEvent = [];
        $logMultipleEvents = [];

        $logger =
            function (object $event, array &$log): void {
                if ($event instanceof Nameable) {
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

        if (\PHP_VERSION_ID >= 80200) {
            $dispatcher->listen(require __DIR__ . '/listenerWithDnfType.php');
        }

        $dispatcher->dispatch(new BaseEvent());
        $dispatcher->dispatch(new MainEvent());
        $dispatcher->dispatch(new NamedEvent(__METHOD__));
        $dispatcher->dispatch(new NamedEvent('onLoad'));
        $dispatcher->dispatch(new NamedEvent('onLogout'));
        $dispatcher->dispatch(new LoggableEvent($logger, $logLoggableEvent));
        $dispatcher->dispatch(new NamedLoggableEvent('onClose', $logger, $logNamedLoggableEvent));

        $this->assertSame([
            BaseEvent::class,
            MainEvent::class,
            [__METHOD__ => NamedEvent::class],
            ['onLoad' => NamedEvent::class],
            ['onLogout' => NamedEvent::class],
            LoggableEvent::class,
            ['onClose' => NamedLoggableEvent::class],
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

        $this->assertSame([
            ['onClose' => NamedLoggableEvent::class],
        ], $logNamedLoggableEvent);

        // Note the absence of the `LoggableEvent&Nameable` event
        /** @var mixed $logMultipleEvents */
        $this->assertSame(\PHP_VERSION_ID >= 80200 ? [
            MainEvent::class,
            [__METHOD__ => NamedEvent::class],
            ['onLoad' => NamedEvent::class],
            ['onLogout' => NamedEvent::class],
        ] : [], $logMultipleEvents);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('At least one event must be given');
        $dispatcher->listen(fn(object $event) => $event);
    }

    public function testFacade(): void
    {
        $received = 0;
        Event::getInstance()->listen(
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
        $this->expectExceptionMessage('$callback has no parameter at position 0');
        $dispatcher = new EventDispatcher();
        $dispatcher->listen(fn() => null);
    }

    /**
     * @requires PHP >= 8.1
     */
    public function testListenerWithIntersectionParameter(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('At least one event must be given');
        $dispatcher = new EventDispatcher();
        $dispatcher->listen(require __DIR__ . '/listenerWithIntersectionType.php');
    }

    protected function tearDown(): void
    {
        if (Event::isLoaded()) {
            Event::unload();
        }
    }
}

class BaseEvent {}
class MainEvent extends BaseEvent {}

trait NamedEventTrait
{
    protected string $Name;

    public function __construct(string $name)
    {
        $this->Name = $name;
    }

    public function name(): string
    {
        return $this->Name;
    }
}

class NamedEvent extends BaseEvent implements Nameable
{
    use NamedEventTrait;
}

class LoggableEvent extends BaseEvent
{
    /** @var callable(self, array<string|array{string,string}>): void */
    protected $Logger;
    /** @var array<string|array{string,string}> */
    protected array $Log;

    /**
     * @param callable(self, array<string|array{string,string}>): void $logger
     * @param array<string|array{string,string}> $log
     */
    public function __construct(callable $logger, array &$log)
    {
        $this->Logger = $logger;
        $this->Log = &$log;
    }

    public function log(): void
    {
        ($this->Logger)($this, $this->Log);
    }
}

class NamedLoggableEvent extends LoggableEvent implements Nameable
{
    use NamedEventTrait;

    public function __construct(string $name, callable $logger, array &$log)
    {
        $this->Name = $name;
        parent::__construct($logger, $log);
    }
}
