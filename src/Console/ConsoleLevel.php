<?php

declare(strict_types=1);

namespace Lkrms\Console;

use Psr\Log\LogLevel;

/**
 *
 * @package Lkrms
 */
class ConsoleLevel
{
    // Values are the same as syslog/journalctl
    public const EMERGENCY = 0;

    public const ALERT = 1;

    public const CRITICAL = 2;

    public const ERROR = 3;

    public const WARNING = 4;

    public const NOTICE = 5;

    public const INFO = 6;

    public const DEBUG = 7;

    public static function ToPsrLogLevel(int $level): string
    {
        switch ($level)
        {
            case self::EMERGENCY:

                return LogLevel::EMERGENCY;

            case self::ALERT:

                return LogLevel::ALERT;

            case self::CRITICAL:

                return LogLevel::CRITICAL;

            case self::ERROR:

                return LogLevel::ERROR;

            case self::WARNING:

                return LogLevel::WARNING;

            case self::NOTICE:

                return LogLevel::NOTICE;

            case self::INFO:

                return LogLevel::INFO;

            case self::DEBUG:

                return LogLevel::DEBUG;
        }
    }
}

