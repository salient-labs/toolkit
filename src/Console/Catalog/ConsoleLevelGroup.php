<?php declare(strict_types=1);

namespace Lkrms\Console\Catalog;

use Lkrms\Concept\Enumeration;
use Lkrms\Console\Catalog\ConsoleLevel as Level;

/**
 * Groups of console message levels
 *
 * @api
 *
 * @extends Enumeration<int[]>
 */
final class ConsoleLevelGroup extends Enumeration
{
    public const ALL = [
        Level::EMERGENCY,
        Level::ALERT,
        Level::CRITICAL,
        Level::ERROR,
        Level::WARNING,
        Level::NOTICE,
        Level::INFO,
        Level::DEBUG,
    ];

    public const ALL_EXCEPT_DEBUG = [
        Level::EMERGENCY,
        Level::ALERT,
        Level::CRITICAL,
        Level::ERROR,
        Level::WARNING,
        Level::NOTICE,
        Level::INFO,
    ];

    public const ERRORS_AND_WARNINGS = [
        Level::EMERGENCY,
        Level::ALERT,
        Level::CRITICAL,
        Level::ERROR,
        Level::WARNING,
    ];

    public const ERRORS = [
        Level::EMERGENCY,
        Level::ALERT,
        Level::CRITICAL,
        Level::ERROR,
    ];

    public const INFO = [
        Level::NOTICE,
        Level::INFO,
        Level::DEBUG,
    ];

    public const INFO_EXCEPT_DEBUG = [
        Level::NOTICE,
        Level::INFO,
    ];

    public const INFO_QUIET = [
        Level::NOTICE,
    ];
}
