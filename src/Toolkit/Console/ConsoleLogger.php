<?php declare(strict_types=1);

namespace Salient\Console;

use Psr\Log\InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Salient\Contract\Console\ConsoleInterface as Console;
use Salient\Contract\Console\ConsoleMessageType as MessageType;
use Salient\Utility\Format;
use Throwable;

/**
 * A PSR-3 logger backed by a console writer
 */
final class ConsoleLogger implements LoggerInterface
{
    private const LOG_LEVEL_MAP = [
        LogLevel::EMERGENCY => Console::LEVEL_EMERGENCY,
        LogLevel::ALERT => Console::LEVEL_ALERT,
        LogLevel::CRITICAL => Console::LEVEL_CRITICAL,
        LogLevel::ERROR => Console::LEVEL_ERROR,
        LogLevel::WARNING => Console::LEVEL_WARNING,
        LogLevel::NOTICE => Console::LEVEL_NOTICE,
        LogLevel::INFO => Console::LEVEL_INFO,
        LogLevel::DEBUG => Console::LEVEL_DEBUG,
    ];

    private Console $Writer;

    public function __construct(Console $writer)
    {
        $this->Writer = $writer;
    }

    /**
     * @inheritDoc
     */
    public function emergency($message, array $context = [])
    {
        $this->log(LogLevel::EMERGENCY, $message, $context);
    }

    /**
     * @inheritDoc
     */
    public function alert($message, array $context = [])
    {
        $this->log(LogLevel::ALERT, $message, $context);
    }

    /**
     * @inheritDoc
     */
    public function critical($message, array $context = [])
    {
        $this->log(LogLevel::CRITICAL, $message, $context);
    }

    /**
     * @inheritDoc
     */
    public function error($message, array $context = [])
    {
        $this->log(LogLevel::ERROR, $message, $context);
    }

    /**
     * @inheritDoc
     */
    public function warning($message, array $context = [])
    {
        $this->log(LogLevel::WARNING, $message, $context);
    }

    /**
     * @inheritDoc
     */
    public function notice($message, array $context = [])
    {
        $this->log(LogLevel::NOTICE, $message, $context);
    }

    /**
     * @inheritDoc
     */
    public function info($message, array $context = [])
    {
        $this->log(LogLevel::INFO, $message, $context);
    }

    /**
     * @inheritDoc
     */
    public function debug($message, array $context = [])
    {
        $this->log(LogLevel::DEBUG, $message, $context);
    }

    /**
     * @inheritDoc
     */
    public function log($level, $message, array $context = [])
    {
        if (!isset(self::LOG_LEVEL_MAP[$level])) {
            throw new InvalidArgumentException('Invalid log level');
        }

        if ($context) {
            foreach ($context as $key => $value) {
                $replace['{' . $key . '}'] = Format::value($value);
            }
            $message = strtr($message, $replace);

            if (
                isset($context['exception'])
                && $context['exception'] instanceof Throwable
            ) {
                $exception = $context['exception'];
            }
        }

        $this->Writer->message(
            (string) $message,
            null,
            self::LOG_LEVEL_MAP[$level],
            MessageType::STANDARD,
            $exception ?? null,
        );
    }
}
