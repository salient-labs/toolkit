<?php declare(strict_types=1);

namespace Lkrms\Utility\Catalog;

use Lkrms\Concept\Enumeration;

/**
 * Env flags
 *
 * @extends Enumeration<int>
 *
 * @see \Lkrms\Utility\Env
 */
final class EnvFlag extends Enumeration
{
    /**
     * Set locale information from the environment
     *
     * Locale names are taken from environment variables `LC_ALL`, `LC_COLLATE`,
     * `LC_CTYPE`, `LC_MONETARY`, `LC_NUMERIC`, `LC_TIME` and `LC_MESSAGES`, or
     * from `LANG`. On Windows, they are taken from the system's language and
     * region settings.
     */
    public const LOCALE = 1;

    /**
     * Set the default timezone from the environment
     *
     * If environment variable `TZ` contains a valid timezone, it is passed to
     * `date_default_timezone_set`.
     */
    public const TIMEZONE = 2;

    /**
     * Apply all recognised values from the environment to the running script
     *
     */
    public const ALL = self::LOCALE | self::TIMEZONE;
}
