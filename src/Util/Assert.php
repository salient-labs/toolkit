<?php

declare(strict_types=1);

namespace Lkrms\Util;

use Lkrms\Core\Utility;
use RuntimeException;
use UnexpectedValueException;

/**
 * Throw an exception if a condition isn't met
 *
 */
final class Assert extends Utility
{
    private static function throwException(string $message, ?string $name): void
    {
        $message = str_replace("{}", $name ? "'$name'" : "value", $message);
        throw new UnexpectedValueException($message);
    }

    /**
     *
     * @param mixed $value
     * @param string|null $name
     * @return void
     * @throws UnexpectedValueException
     */
    public static function notNull($value, string $name = null): void
    {
        if (is_null($value))
        {
            self::throwException("{} cannot be null", $name);
        }
    }

    /**
     *
     * @param mixed $value
     * @param string|null $name
     * @return void
     * @throws UnexpectedValueException
     */
    public static function notEmpty($value, string $name = null): void
    {
        if (empty($value))
        {
            self::throwException("{} cannot be empty", $name);
        }
    }

    /**
     *
     * @param null|string $value
     * @param string $pattern
     * @param string|null $name
     * @param string $message
     * @return void
     * @throws UnexpectedValueException
     */
    public static function patternMatches(
        ?string $value,
        string $pattern,
        string $name    = null,
        string $message = "must match pattern '{}'"
    ): void
    {
        if (is_null($value) || !preg_match($pattern, $value))
        {
            $message = str_replace("{}", $pattern, $message);
            self::throwException("{} $message", $name);
        }
    }

    /**
     *
     * @param mixed $value
     * @param string|null $name
     * @return void
     * @throws UnexpectedValueException
     */
    public static function isArray($value, string $name = null): void
    {
        if (!is_array($value))
        {
            self::throwException("{} must be an array", $name);
        }
    }

    /**
     *
     * @return void
     * @throws RuntimeException
     */
    public static function sapiIsCli(): void
    {
        if (PHP_SAPI != "cli")
        {
            throw new RuntimeException("CLI required");
        }
    }

    /**
     *
     * @return void
     * @throws RuntimeException
     */
    public static function localeIsUtf8(): void
    {
        if (($locale = setlocale(LC_CTYPE, "")) === false)
        {
            throw new RuntimeException("Invalid locale (check LANG and LC_*)");
        }

        if (!preg_match('/\.UTF-?8$/i', $locale))
        {
            throw new RuntimeException("'$locale' is not a UTF-8 locale");
        }
    }
}
