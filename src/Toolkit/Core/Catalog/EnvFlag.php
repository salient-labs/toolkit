<?php declare(strict_types=1);

namespace Salient\Core\Catalog;

use Salient\Core\Utility\Env;
use Salient\Core\AbstractEnumeration;

/**
 * Env::apply() flags
 *
 * @extends AbstractEnumeration<int>
 */
final class EnvFlag extends AbstractEnumeration
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
     */
    public const ALL = self::LOCALE | self::TIMEZONE;
}
