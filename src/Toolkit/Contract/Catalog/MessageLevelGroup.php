<?php declare(strict_types=1);

namespace Salient\Contract\Catalog;

use Salient\Contract\Catalog\HasMessageLevel as Level;

/**
 * @api
 */
interface MessageLevelGroup
{
    /**
     * @var list<Level::*>
     */
    public const ALL = [
        Level::LEVEL_EMERGENCY,
        Level::LEVEL_ALERT,
        Level::LEVEL_CRITICAL,
        Level::LEVEL_ERROR,
        Level::LEVEL_WARNING,
        Level::LEVEL_NOTICE,
        Level::LEVEL_INFO,
        Level::LEVEL_DEBUG,
    ];

    /**
     * @var list<Level::*>
     */
    public const ALL_EXCEPT_DEBUG = [
        Level::LEVEL_EMERGENCY,
        Level::LEVEL_ALERT,
        Level::LEVEL_CRITICAL,
        Level::LEVEL_ERROR,
        Level::LEVEL_WARNING,
        Level::LEVEL_NOTICE,
        Level::LEVEL_INFO,
    ];

    /**
     * @var list<Level::*>
     */
    public const ERRORS_AND_WARNINGS = [
        Level::LEVEL_EMERGENCY,
        Level::LEVEL_ALERT,
        Level::LEVEL_CRITICAL,
        Level::LEVEL_ERROR,
        Level::LEVEL_WARNING,
    ];

    /**
     * @var list<Level::*>
     */
    public const ERRORS = [
        Level::LEVEL_EMERGENCY,
        Level::LEVEL_ALERT,
        Level::LEVEL_CRITICAL,
        Level::LEVEL_ERROR,
    ];

    /**
     * @var list<Level::*>
     */
    public const INFO = [
        Level::LEVEL_NOTICE,
        Level::LEVEL_INFO,
        Level::LEVEL_DEBUG,
    ];

    /**
     * @var list<Level::*>
     */
    public const INFO_EXCEPT_DEBUG = [
        Level::LEVEL_NOTICE,
        Level::LEVEL_INFO,
    ];

    /**
     * @var list<Level::*>
     */
    public const INFO_QUIET = [
        Level::LEVEL_NOTICE,
    ];
}
