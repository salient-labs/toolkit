<?php declare(strict_types=1);

namespace Salient\Console\Catalog;

use Salient\Console\Catalog\ConsoleLevel as Level;
use Salient\Core\AbstractEnumeration;

/**
 * Groups of console message levels
 *
 * @api
 *
 * @extends AbstractEnumeration<int[]>
 */
final class ConsoleLevelGroup extends AbstractEnumeration
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
