<?php declare(strict_types=1);

namespace Salient\Contract\Core;

/**
 * Message levels
 *
 * Levels have the same value as their syslog / journalctl counterparts.
 *
 * @api
 */
interface MessageLevel
{
    public const EMERGENCY = 0;
    public const ALERT = 1;
    public const CRITICAL = 2;
    public const ERROR = 3;
    public const WARNING = 4;
    public const NOTICE = 5;
    public const INFO = 6;
    public const DEBUG = 7;
}
