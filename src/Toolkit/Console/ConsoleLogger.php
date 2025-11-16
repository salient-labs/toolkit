<?php declare(strict_types=1);

namespace Salient\Console;

use Psr\Log\InvalidArgumentException as PsrInvalidArgumentException;
use Psr\Log\LoggerInterface as PsrLoggerInterface;
use Psr\Log\LogLevel as PsrLogLevel;
use Salient\Contract\Console\ConsoleInterface as Console;
use Salient\Utility\Format;
use Throwable;

/**
 * @api
 */
class ConsoleLogger implements PsrLoggerInterface
{
    private const LOG_LEVEL_MAP = [
        PsrLogLevel::EMERGENCY => Console::LEVEL_EMERGENCY,
        PsrLogLevel::ALERT => Console::LEVEL_ALERT,
        PsrLogLevel::CRITICAL => Console::LEVEL_CRITICAL,
        PsrLogLevel::ERROR => Console::LEVEL_ERROR,
        PsrLogLevel::WARNING => Console::LEVEL_WARNING,
        PsrLogLevel::NOTICE => Console::LEVEL_NOTICE,
        PsrLogLevel::INFO => Console::LEVEL_INFO,
        PsrLogLevel::DEBUG => Console::LEVEL_DEBUG,
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
        $this->log(PsrLogLevel::EMERGENCY, $message, $context);
    }

    /**
     * @inheritDoc
     */
    public function alert($message, array $context = [])
    {
        $this->log(PsrLogLevel::ALERT, $message, $context);
    }

    /**
     * @inheritDoc
     */
    public function critical($message, array $context = [])
    {
        $this->log(PsrLogLevel::CRITICAL, $message, $context);
    }

    /**
     * @inheritDoc
     */
    public function error($message, array $context = [])
    {
        $this->log(PsrLogLevel::ERROR, $message, $context);
    }

    /**
     * @inheritDoc
     */
    public function warning($message, array $context = [])
    {
        $this->log(PsrLogLevel::WARNING, $message, $context);
    }

    /**
     * @inheritDoc
     */
    public function notice($message, array $context = [])
    {
        $this->log(PsrLogLevel::NOTICE, $message, $context);
    }

    /**
     * @inheritDoc
     */
    public function info($message, array $context = [])
    {
        $this->log(PsrLogLevel::INFO, $message, $context);
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
        if (!is_string($level) || !isset(self::LOG_LEVEL_MAP[$level])) {
            throw new PsrInvalidArgumentException('Invalid log level');
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
