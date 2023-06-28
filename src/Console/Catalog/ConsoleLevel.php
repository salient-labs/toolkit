<?php declare(strict_types=1);

namespace Lkrms\Console\Catalog;

use Lkrms\Concept\ConvertibleEnumeration;
use LogicException;
use Psr\Log\LogLevel;

/**
 * Console message levels
 *
 * Constants have the same values as their syslog / journalctl counterparts.
 *
 * @extends ConvertibleEnumeration<int>
 */
final class ConsoleLevel extends ConvertibleEnumeration
{
    public const EMERGENCY = 0;
    public const ALERT = 1;
    public const CRITICAL = 2;
    public const ERROR = 3;
    public const WARNING = 4;
    public const NOTICE = 5;
    public const INFO = 6;
    public const DEBUG = 7;

    protected static $NameMap = [
        self::EMERGENCY => 'EMERGENCY',
        self::ALERT => 'ALERT',
        self::CRITICAL => 'CRITICAL',
        self::ERROR => 'ERROR',
        self::WARNING => 'WARNING',
        self::NOTICE => 'NOTICE',
        self::INFO => 'INFO',
        self::DEBUG => 'DEBUG',
    ];

    protected static $ValueMap = [
        'EMERGENCY' => self::EMERGENCY,
        'ALERT' => self::ALERT,
        'CRITICAL' => self::CRITICAL,
        'ERROR' => self::ERROR,
        'WARNING' => self::WARNING,
        'NOTICE' => self::NOTICE,
        'INFO' => self::INFO,
        'DEBUG' => self::DEBUG,
    ];

    private const LOG_LEVEL_MAP = [
        self::EMERGENCY => LogLevel::EMERGENCY,
        self::ALERT => LogLevel::ALERT,
        self::CRITICAL => LogLevel::CRITICAL,
        self::ERROR => LogLevel::ERROR,
        self::WARNING => LogLevel::WARNING,
        self::NOTICE => LogLevel::NOTICE,
        self::INFO => LogLevel::INFO,
        self::DEBUG => LogLevel::DEBUG,
    ];

    public static function toCode(int $level, int $width = 1): string
    {
        if ((self::$NameMap[$level] ?? null) === null) {
            throw new LogicException("Invalid ConsoleLevel: $level");
        }
        return sprintf("%0{$width}d", $level);
    }

    public static function toPsrLogLevel(int $level): string
    {
        if (($logLevel = self::LOG_LEVEL_MAP[$level] ?? null) === null) {
            throw new LogicException("Invalid ConsoleLevel: $level");
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
