<?php declare(strict_types=1);

namespace Salient\Console;

use Psr\Log\InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Salient\Contract\Console\ConsoleInterface as Console;
use Salient\Utility\Format;
use Throwable;

/**
 * @api
 */
class ConsoleLogger implements LoggerInterface
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

    private Console $Console;

    /**
     * @api
     */
    public function __construct(Console $console)
    {
        $this->Console = $console;
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
        $msg1 = $this->applyContext((string) $message, $context, $ex);
        $this->Console->debug($msg1, null, $ex, 1);
    }

    /**
     * @inheritDoc
     */
    public function log($level, $message, array $context = [])
    {
        if (!isset(self::LOG_LEVEL_MAP[$level])) {
            throw new InvalidArgumentException('Invalid log level');
        }

        $msg1 = $this->applyContext((string) $message, $context, $ex);
        $level = self::LOG_LEVEL_MAP[$level];
        $this->Console->message($msg1, null, $level, Console::TYPE_STANDARD, $ex);
    }

    /**
     * @param mixed[] $context
     */
    private function applyContext(string $message, array $context, ?Throwable &$ex): string
    {
        if ($context) {
            foreach ($context as $key => $value) {
                $replace['{' . $key . '}'] = Format::value($value);
            }
            $message = strtr($message, $replace);

            if (
                isset($context['exception'])
                && $context['exception'] instanceof Throwable
            ) {
                $ex = $context['exception'];
            }
        }

        return $message;
    }
}
