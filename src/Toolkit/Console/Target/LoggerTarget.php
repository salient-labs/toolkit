<?php declare(strict_types=1);

namespace Salient\Console\Target;

use Psr\Log\LoggerAwareInterface as PsrLoggerAwareInterface;
use Psr\Log\LoggerInterface as PsrLoggerInterface;
use Psr\Log\LogLevel as PsrLogLevel;
use Salient\Contract\Console\ConsoleInterface as Console;

/**
 * Writes console output to a PSR-3 logger
 *
 * @api
 */
class LoggerTarget extends AbstractTarget implements PsrLoggerAwareInterface
{
    /**
     * @var array<Console::LEVEL_*,PsrLogLevel::*>
     */
    private const LOG_LEVEL_MAP = [
        Console::LEVEL_EMERGENCY => PsrLogLevel::EMERGENCY,
        Console::LEVEL_ALERT => PsrLogLevel::ALERT,
        Console::LEVEL_CRITICAL => PsrLogLevel::CRITICAL,
        Console::LEVEL_ERROR => PsrLogLevel::ERROR,
        Console::LEVEL_WARNING => PsrLogLevel::WARNING,
        Console::LEVEL_NOTICE => PsrLogLevel::NOTICE,
        Console::LEVEL_INFO => PsrLogLevel::INFO,
        Console::LEVEL_DEBUG => PsrLogLevel::DEBUG,
    ];

    private PsrLoggerInterface $Logger;

    /**
     * @api
     */
    public function __construct(PsrLoggerInterface $logger)
    {
        $this->setLogger($logger);
    }

    /**
     * @inheritDoc
     */
    public function setLogger(PsrLoggerInterface $logger): void
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
