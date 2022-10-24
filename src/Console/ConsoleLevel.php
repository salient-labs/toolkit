<?php

declare(strict_types=1);

namespace Lkrms\Console;

use Lkrms\Concept\Enumeration;
use Lkrms\Concern\IsConvertibleEnumeration;
use Lkrms\Contract\IConvertibleEnumeration;
use Psr\Log\LogLevel;
use UnexpectedValueException;

/**
 * Console message levels
 *
 * Constants have the same values as their syslog / journalctl counterparts.
 *
 */
final class ConsoleLevel extends Enumeration implements IConvertibleEnumeration
{
    use IsConvertibleEnumeration;

    public const EMERGENCY = 0;
    public const ALERT     = 1;
    public const CRITICAL  = 2;
    public const ERROR     = 3;
    public const WARNING   = 4;
    public const NOTICE    = 5;
    public const INFO      = 6;
    public const DEBUG     = 7;

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

    protected static $NameMap = [
        self::EMERGENCY => "EMERGENCY",
        self::ALERT     => "ALERT",
        self::CRITICAL  => "CRITICAL",
        self::ERROR     => "ERROR",
        self::WARNING   => "WARNING",
        self::NOTICE    => "NOTICE",
        self::INFO      => "INFO",
        self::DEBUG     => "DEBUG",
    ];

    protected static $ValueMap = [
        "emergency" => self::EMERGENCY,
        "alert"     => self::ALERT,
        "critical"  => self::CRITICAL,
        "error"     => self::ERROR,
        "warning"   => self::WARNING,
        "notice"    => self::NOTICE,
        "info"      => self::INFO,
        "debug"     => self::DEBUG,
    ];

    public static function toCode(int $level, int $width = 1): string
    {
        if (!array_key_exists($level, self::$NameMap))
        {
            throw new UnexpectedValueException("Invalid ConsoleLevel: $level");
        }

        return sprintf("%0{$width}d", $level);
    }

    public static function toPsrLogLevel(int $level): string
    {
        if (is_null($logLevel = self::LOG_LEVEL_MAP[$level] ?? null))
        {
            throw new UnexpectedValueException("Invalid ConsoleLevel: $level");
        }

        return $logLevel;
    }

    /**
     * @return int[]
     */
    public static function getAll(): array
    {
        return [
            self::EMERGENCY,
            self::ALERT,
            self::CRITICAL,
            self::ERROR,
            self::WARNING,
            self::NOTICE,
            self::INFO,
            self::DEBUG,
        ];
    }
}
