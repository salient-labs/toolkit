<?php

declare(strict_types=1);

namespace Lkrms\Console;

use Psr\Log\LogLevel;
use UnexpectedValueException;

/**
 * Console message levels
 *
 * Constants have the same values as their syslog / journalctl counterparts.
 *
 */
abstract class ConsoleLevel
{
    public const EMERGENCY = 0;
    public const ALERT = 1;
    public const CRITICAL = 2;
    public const ERROR = 3;
    public const WARNING = 4;
    public const NOTICE = 5;
    public const INFO = 6;
    public const DEBUG = 7;

    private const LOG_LEVEL_MAP = [
        self::EMERGENCY => LogLevel::EMERGENCY,
        self::ALERT     => LogLevel::ALERT,
        self::CRITICAL  => LogLevel::CRITICAL,
        self::ERROR     => LogLevel::ERROR,
        self::WARNING   => LogLevel::WARNING,
        self::NOTICE    => LogLevel::NOTICE,
        self::INFO      => LogLevel::INFO,
        self::DEBUG     => LogLevel::DEBUG,
    ];

    public static function toPsrLogLevel(int $level): string
    {
        if (is_null($logLevel = self::LOG_LEVEL_MAP[$level] ?? null))
        {
            throw new UnexpectedValueException("Invalid level: $level");
        }

        return $logLevel;
    }
}
