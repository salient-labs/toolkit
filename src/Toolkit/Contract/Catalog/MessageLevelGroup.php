<?php declare(strict_types=1);

namespace Salient\Contract\Catalog;

use Salient\Contract\Catalog\MessageLevel as Level;

/**
 * Groups of message levels
 *
 * @api
 */
interface MessageLevelGroup
{
    /**
     * @var list<Level::*>
     */
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

    /**
     * @var list<Level::*>
     */
    public const ALL_EXCEPT_DEBUG = [
        Level::EMERGENCY,
        Level::ALERT,
        Level::CRITICAL,
        Level::ERROR,
        Level::WARNING,
        Level::NOTICE,
        Level::INFO,
    ];

    /**
     * @var list<Level::*>
     */
    public const ERRORS_AND_WARNINGS = [
        Level::EMERGENCY,
        Level::ALERT,
        Level::CRITICAL,
        Level::ERROR,
        Level::WARNING,
    ];

    /**
     * @var list<Level::*>
     */
    public const ERRORS = [
        Level::EMERGENCY,
        Level::ALERT,
        Level::CRITICAL,
        Level::ERROR,
    ];

    /**
     * @var list<Level::*>
     */
    public const INFO = [
        Level::NOTICE,
        Level::INFO,
        Level::DEBUG,
    ];

    /**
     * @var list<Level::*>
     */
    public const INFO_EXCEPT_DEBUG = [
        Level::NOTICE,
        Level::INFO,
    ];

    /**
     * @var list<Level::*>
     */
    public const INFO_QUIET = [
        Level::NOTICE,
    ];
}
