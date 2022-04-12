<?php

declare(strict_types=1);

namespace Lkrms;

use RuntimeException;
use UnexpectedValueException;

/**
 * Throw an exception if a condition isn't met
 *
 * @package Lkrms
 */
abstract class Assert
{
    private static function throwUnexpectedValue(string $message, ?string $name)
    {
        $message = str_replace("{}", is_null($name) ? "value" : "'$name'", $message);
        throw new UnexpectedValueException($message);
    }

    /**
     *
     * @param mixed $value
     * @param string|null $name
     */
    public static function notNull($value, string $name = null)
    {
        if (is_null($value))
        {
            self::throwUnexpectedValue("{} cannot be null", $name);
        }
    }

    /**
     *
     * @param mixed $value
     * @param string|null $name
     */
    public static function notEmpty($value, string $name = null)
    {
        if (empty($value))
        {
            self::throwUnexpectedValue("{} cannot be empty", $name);
        }
    }

    /**
     *
     * @param null|string $value
     * @param string $pattern
     * @param string|null $name
     * @param string $message
     */
    public static function pregMatch(?string $value, string $pattern, string $name = null, string $message = "must match pattern '{}'")
    {
        if (is_null($value) || !preg_match($pattern, $value))
        {
            $message = str_replace("{}", $pattern, $message);
            self::throwUnexpectedValue("{} $message", $name);
        }
    }

    /**
     *
     * @param mixed $value
     * @param string|null $name
     */
    public static function isArray($value, string $name = null)
    {
        if (!is_array($value))
        {
            self::throwUnexpectedValue("{} must be an array", $name);
        }
    }

    public static function sapiIsCli()
    {
        if (PHP_SAPI != "cli")
        {
            throw new RuntimeException("CLI required");
        }
    }

    public static function localeIsUtf8()
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

