<?php declare(strict_types=1);

namespace Salient\Console\Catalog;

use Salient\Core\AbstractConvertibleEnumeration;

/**
 * Console message levels
 *
 * Levels have the same value as their syslog / journalctl counterparts.
 *
 * @api
 *
 * @extends AbstractConvertibleEnumeration<int>
 */
final class ConsoleLevel extends AbstractConvertibleEnumeration
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
}
