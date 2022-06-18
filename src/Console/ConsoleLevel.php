<?php

declare(strict_types=1);

namespace Lkrms\Console;

use Lkrms\Concept\Enumeration;
use UnexpectedValueException;

/**
 * Console message levels
 *
 * Constants have the same values as their syslog / journalctl counterparts.
 *
 */
final class ConsoleLevel extends Enumeration
{
    public const EMERGENCY = 0;
    public const ALERT     = 1;
    public const CRITICAL  = 2;
    public const ERROR     = 3;
    public const WARNING   = 4;
    public const NOTICE    = 5;
    public const INFO      = 6;
    public const DEBUG     = 7;

    private const LOG_LEVEL_MAP = [
        self::EMERGENCY => \Psr\Log\LogLevel::EMERGENCY,
        self::ALERT     => \Psr\Log\LogLevel::ALERT,
        self::CRITICAL  => \Psr\Log\LogLevel::CRITICAL,
        self::ERROR     => \Psr\Log\LogLevel::ERROR,
        self::WARNING   => \Psr\Log\LogLevel::WARNING,
        self::NOTICE    => \Psr\Log\LogLevel::NOTICE,
        self::INFO      => \Psr\Log\LogLevel::INFO,
        self::DEBUG     => \Psr\Log\LogLevel::DEBUG,
    ];

    public static function toPsrLogLevel(int $level): string
    {
        if (is_null($logLevel = self::LOG_LEVEL_MAP[$level] ?? null))
        {
            throw new UnexpectedValueException("Invalid ConsoleLevel: $level");
        }

        return $logLevel;
    }
}
