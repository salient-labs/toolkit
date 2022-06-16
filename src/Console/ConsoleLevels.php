<?php

declare(strict_types=1);

namespace Lkrms\Console;

use Lkrms\Concept\Enumeration;

/**
 * Groups of console message levels
 *
 */
final class ConsoleLevels extends Enumeration
{
    public const ALL = [
        ConsoleLevel::EMERGENCY,
        ConsoleLevel::ALERT,
        ConsoleLevel::CRITICAL,
        ConsoleLevel::ERROR,
        ConsoleLevel::WARNING,
        ConsoleLevel::NOTICE,
        ConsoleLevel::INFO,
    ];

    public const ALL_DEBUG = [
        ConsoleLevel::EMERGENCY,
        ConsoleLevel::ALERT,
        ConsoleLevel::CRITICAL,
        ConsoleLevel::ERROR,
        ConsoleLevel::WARNING,
        ConsoleLevel::NOTICE,
        ConsoleLevel::INFO,
        ConsoleLevel::DEBUG,
    ];

    public const ERRORS = [
        ConsoleLevel::EMERGENCY,
        ConsoleLevel::ALERT,
        ConsoleLevel::CRITICAL,
        ConsoleLevel::ERROR,
        ConsoleLevel::WARNING,
    ];

    public const INFO_QUIET = [
        ConsoleLevel::NOTICE,
    ];

    public const INFO = [
        ConsoleLevel::NOTICE,
        ConsoleLevel::INFO,
    ];

    public const INFO_DEBUG = [
        ConsoleLevel::NOTICE,
        ConsoleLevel::INFO,
        ConsoleLevel::DEBUG,
    ];
}
