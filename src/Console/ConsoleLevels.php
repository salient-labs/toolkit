<?php

declare(strict_types=1);

namespace Lkrms\Console;

/**
 * Groups of console message levels
 *
 */
abstract class ConsoleLevels
{
    public const ALL = [
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
