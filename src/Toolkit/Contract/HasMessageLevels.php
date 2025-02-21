<?php declare(strict_types=1);

namespace Salient\Contract;

use Salient\Contract\HasMessageLevel as Level;

/**
 * @api
 */
interface HasMessageLevels
{
    public const LEVELS_ALL = [
        Level::LEVEL_EMERGENCY,
        Level::LEVEL_ALERT,
        Level::LEVEL_CRITICAL,
        Level::LEVEL_ERROR,
        Level::LEVEL_WARNING,
        Level::LEVEL_NOTICE,
        Level::LEVEL_INFO,
        Level::LEVEL_DEBUG,
    ];

    public const LEVELS_ALL_EXCEPT_DEBUG = [
        Level::LEVEL_EMERGENCY,
        Level::LEVEL_ALERT,
        Level::LEVEL_CRITICAL,
        Level::LEVEL_ERROR,
        Level::LEVEL_WARNING,
        Level::LEVEL_NOTICE,
        Level::LEVEL_INFO,
    ];

    public const LEVELS_ERRORS_AND_WARNINGS = [
        Level::LEVEL_EMERGENCY,
        Level::LEVEL_ALERT,
        Level::LEVEL_CRITICAL,
        Level::LEVEL_ERROR,
        Level::LEVEL_WARNING,
    ];

    public const LEVELS_ERRORS = [
        Level::LEVEL_EMERGENCY,
        Level::LEVEL_ALERT,
        Level::LEVEL_CRITICAL,
        Level::LEVEL_ERROR,
    ];

    public const LEVELS_INFO = [
        Level::LEVEL_NOTICE,
        Level::LEVEL_INFO,
        Level::LEVEL_DEBUG,
    ];

    public const LEVELS_INFO_EXCEPT_DEBUG = [
        Level::LEVEL_NOTICE,
        Level::LEVEL_INFO,
    ];

    public const LEVELS_INFO_QUIET = [
        Level::LEVEL_NOTICE,
    ];
}
