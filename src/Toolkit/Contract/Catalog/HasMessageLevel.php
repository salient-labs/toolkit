<?php declare(strict_types=1);

namespace Salient\Contract\Catalog;

/**
 * @api
 */
interface HasMessageLevel
{
    public const LEVEL_EMERGENCY = 0;
    public const LEVEL_ALERT = 1;
    public const LEVEL_CRITICAL = 2;
    public const LEVEL_ERROR = 3;
    public const LEVEL_WARNING = 4;
    public const LEVEL_NOTICE = 5;
    public const LEVEL_INFO = 6;
    public const LEVEL_DEBUG = 7;
}
