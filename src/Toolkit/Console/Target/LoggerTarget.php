<?php declare(strict_types=1);

namespace Salient\Console\Target;

use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Salient\Console\Concept\ConsoleTarget;
use Salient\Contract\Core\MessageLevel as Level;

/**
 * Writes console output to a PSR-3 logger
 */
final class LoggerTarget extends ConsoleTarget implements LoggerAwareInterface
{
    /**
     * @var array<Level::*,LogLevel::*>
     */
    private const LOG_LEVEL_MAP = [
        Level::EMERGENCY => LogLevel::EMERGENCY,
        Level::ALERT => LogLevel::ALERT,
        Level::CRITICAL => LogLevel::CRITICAL,
        Level::ERROR => LogLevel::ERROR,
        Level::WARNING => LogLevel::WARNING,
        Level::NOTICE => LogLevel::NOTICE,
        Level::INFO => LogLevel::INFO,
        Level::DEBUG => LogLevel::DEBUG,
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
    public function write($level, string $message, array $context = []): void
    {
        $this->Logger->log(self::LOG_LEVEL_MAP[$level], $message, $context);
    }
}
