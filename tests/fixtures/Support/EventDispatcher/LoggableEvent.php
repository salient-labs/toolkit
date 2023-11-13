<?php declare(strict_types=1);

namespace Lkrms\Tests\Support\EventDispatcher;

class LoggableEvent extends BaseEvent
{
    /**
     * @var callable(self, array<string|array{string,string}>): void
     */
    protected $Logger;

    /**
     * @var array<string|array{string,string}>
     */
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
