<?php declare(strict_types=1);

namespace Salient\Console\Target;

use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Salient\Contract\Console\ConsoleInterface as Console;

/**
 * Writes console output to a PSR-3 logger
 */
final class LoggerTarget extends ConsoleTarget implements LoggerAwareInterface
{
    /**
     * @var array<Console::LEVEL_*,LogLevel::*>
     */
    private const LOG_LEVEL_MAP = [
        Console::LEVEL_EMERGENCY => LogLevel::EMERGENCY,
        Console::LEVEL_ALERT => LogLevel::ALERT,
        Console::LEVEL_CRITICAL => LogLevel::CRITICAL,
        Console::LEVEL_ERROR => LogLevel::ERROR,
        Console::LEVEL_WARNING => LogLevel::WARNING,
        Console::LEVEL_NOTICE => LogLevel::NOTICE,
        Console::LEVEL_INFO => LogLevel::INFO,
        Console::LEVEL_DEBUG => LogLevel::DEBUG,
    ];

    private LoggerInterface $Logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->setLogger($logger);
    }

    /**
     * @inheritDoc
     */
    public function setLogger(LoggerInterface $logger)
    {
        $this->Logger = $logger;
    }

    /**
     * @inheritDoc
     */
    public function write(int $level, string $message, array $context = []): void
    {
        $this->Logger->log(self::LOG_LEVEL_MAP[$level], $message, $context);
    }
}
