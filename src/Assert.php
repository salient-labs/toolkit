<?php

declare(strict_types=1);

namespace Lkrms;

use Exception;

/**
 * Assertion functions
 *
 * @package Lkrms
 */
class Assert
{
    private static function GetName(?string $name): string
    {
        return is_null($name) ? "value" : "'$name'";
    }

    public static function NotNull($value, string $name = null)
    {
        if (is_null($value))
        {
            $name = self::GetName($name);
            throw new Exception("$name can't be null");
        }
    }

    public static function NotEmpty($value, string $name = null)
    {
        if (empty($value))
        {
            $name = self::GetName($name);
            throw new Exception("$name can't be empty");
        }
    }

    public static function PregMatch(?string $value, string $pattern, string $name = null)
    {
        if (is_null($value) || ! preg_match($pattern, $value))
        {
            $name = self::GetName($name);
            throw new Exception("$name must match pattern '$pattern'");
        }
    }

    public static function ExactStringLength($value, int $length, string $name = null)
    {
        if ( ! is_string($value) || strlen($value) != $length)
        {
            $name = self::GetName($name);
            throw new Exception("$name must be a string with length $length");
        }
    }

    public static function MinimumStringLength($value, int $minLength, string $name = null)
    {
        if ( ! is_string($value) || strlen($value) < $minLength)
        {
            $name = self::GetName($name);
            throw new Exception("$name must be a string with length at least $minLength");
        }
    }

    public static function IsArray($value, string $name = null)
    {
        if ( ! is_array($value))
        {
            $name = self::GetName($name);
            throw new Exception("$name must be an array");
        }
    }

    public static function SapiIsCli()
    {
        if (PHP_SAPI != "cli")
        {
            throw new Exception("CLI required");
        }
    }

    public static function LocaleIsUtf8()
    {
        $locale = setlocale(LC_CTYPE, "");

        if ( ! preg_match('/\.UTF-?8$/i', $locale))
        {
            throw new Exception("Locale '$locale' does not support UTF-8");
        }
    }
}

