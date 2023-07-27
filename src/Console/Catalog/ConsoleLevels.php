<?php declare(strict_types=1);

namespace Lkrms\Console\Catalog;

use Lkrms\Concept\Enumeration;

/**
 * Groups of console message levels
 *
 * @extends Enumeration<int[]>
 */
final class ConsoleLevels extends Enumeration
{
    public const DEBUG_ALL = [
        ...self::ALL,
        ConsoleLevel::DEBUG,
    ];

    public const DEBUG_INFO = [
        ...self::INFO,
        ConsoleLevel::DEBUG,
    ];

    public const ALL = [
        ConsoleLevel::EMERGENCY,
        ConsoleLevel::ALERT,
        ConsoleLevel::CRITICAL,
        ConsoleLevel::ERROR,
        ConsoleLevel::WARNING,
        ConsoleLevel::NOTICE,
        ConsoleLevel::INFO,
    ];

    public const ERRORS = [
        ConsoleLevel::EMERGENCY,
        ConsoleLevel::ALERT,
        ConsoleLevel::CRITICAL,
        ConsoleLevel::ERROR,
        ConsoleLevel::WARNING,
    ];

    public const INFO = [
        ConsoleLevel::NOTICE,
        ConsoleLevel::INFO,
    ];

    public const INFO_QUIET = [
        ConsoleLevel::NOTICE,
    ];
}
