<?php

declare(strict_types=1);

namespace Lkrms\Console;

use Psr\Log\LogLevel;

class ConsoleLevel
{
    // Values are the same as syslog/journalctl
    const EMERGENCY = 0;

    const ALERT = 1;

    const CRITICAL = 2;

    const ERROR = 3;

    const WARNING = 4;

    const NOTICE = 5;

    const INFO = 6;

    const DEBUG = 7;

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

