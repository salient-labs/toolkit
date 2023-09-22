<?php declare(strict_types=1);

namespace Lkrms\Console\Catalog;

use Lkrms\Concept\ConvertibleEnumeration;
use Psr\Log\LogLevel;
use LogicException;

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

    /**
     * @var array<ConsoleLevel::*,string>
     */
    private static $LogLevelMap = [
        self::EMERGENCY => LogLevel::EMERGENCY,
        self::ALERT => LogLevel::ALERT,
        self::CRITICAL => LogLevel::CRITICAL,
        self::ERROR => LogLevel::ERROR,
        self::WARNING => LogLevel::WARNING,
        self::NOTICE => LogLevel::NOTICE,
        self::INFO => LogLevel::INFO,
        self::DEBUG => LogLevel::DEBUG,
    ];

    /**
     * Get the PSR log level that corresponds to a console message level
     *
     * @param ConsoleLevel::* $level
     */
    public static function toPsrLogLevel($level): string
    {
        $logLevel = self::$LogLevelMap[$level] ?? null;
        if ($logLevel === null) {
            throw new LogicException(sprintf('Invalid ConsoleLevel: %d', $level));
        }
        return $logLevel;
    }
}
