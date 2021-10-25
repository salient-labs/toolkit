<?php

declare(strict_types=1);

namespace Lkrms;

use DateTimeZone;
use Exception;
use RuntimeException;
use UnexpectedValueException;

/**
 * Environment-related functions
 *
 * @package Lkrms
 */
class Env
{
    private static $HonourTimezone = true;

    /**
     * Prevent {@link Env::Load()} using environment variable TZ to set the
     * default timezone
     */
    public static function IgnoreTimezone()
    {
        self::$HonourTimezone = false;
    }

    /**
     * Load environment variables from `.env` to `getenv()`, `$_ENV` and
     * `$_SERVER`
     *
     * Each line in the .env file should be a shell-compatible variable
     * assignment. Unquoted values cannot contain whitespace, `"`, `'`, `$`,
     * backticks or glob characters. Double-quoted values cannot contain `"`,
     * `$`, or backticks unless they are escaped. Single-quoted values may
     * contain single quotes as long as they look like this: `'\''`. Lines
     * starting with `#` are ignored.
     *
     * @param string $filename Path to the .env file to load.
     * @param bool $replace If `true`, override existing environment variables.
     * @return void
     * @throws RuntimeException
     * @throws UnexpectedValueException
     */
    public static function Load(string $filename, bool $replace = false): void
    {
        $lines = file($filename, FILE_IGNORE_NEW_LINES);

        if ($lines === false)
        {
            throw new RuntimeException("Could not open $filename");
        }

        foreach ($lines as $i => $line)
        {
            $l = $i + 1;

            if ( ! trim($line) || substr($line, 0, 1) == "#")
            {
                continue;
            }
            elseif ( ! preg_match("/^([A-Z_][A-Z0-9_]*)=(\"(([^\"\$`]|\\\\[\"\$`])*)\"|'(([^']|'\\\\'')*)'|[^]\"\$'*?`\\s[]*)\$/i", $line, $match))
            {
                throw new UnexpectedValueException("Invalid entry at line $l in $filename");
            }

            $name = $match[1];

            if ( ! $replace && (getenv($name) !== false || array_key_exists($name, $_ENV) || array_key_exists($name, $_SERVER)))
            {
                continue;
            }

            if ($match[3] ?? null)
            {
                $value = preg_replace("/\\\\([\"\$`])/", "\\1", $match[3]);
            }
            elseif ($match[5] ?? null)
            {
                $value = str_replace("'\\''", "'", $match[5]);
            }
            else
            {
                $value = $match[2];
            }

            putenv($name . "=" . $value);
            $_ENV[$name]    = $value;
            $_SERVER[$name] = $value;
        }

        if (self::$HonourTimezone && $tz = preg_replace("/^:?(.*\/zoneinfo\/)?/", "", self::Get("TZ", "")))
        {
            try
            {
                $timezone = new DateTimeZone($tz);
                date_default_timezone_set($timezone->getName());
            }
            catch (Exception $ex)
            {
                Console::Debug("Not a valid timezone:", $tz, $ex);
            }
        }
    }

    /**
     * Retrieve an environment variable
     *
     * Looks for `$name` in `$_ENV`, `$_SERVER` and `getenv()`, in that order,
     * and returns the first value it finds, throwing an exception if `$name`
     * isn't set and no `$default` is given.
     *
     * @param string $name The environment variable to retrieve.
     * @param string|null $default The value to return if `$name` isn't set.
     * @return string
     * @throws RuntimeException
     */
    public static function Get(string $name, string $default = null): string
    {
        $value = $_ENV[$name] ?? $_SERVER[$name] ?? (getenv($name, true) ?: getenv($name));

        if ($value === false)
        {
            if (is_null($default))
            {
                throw new RuntimeException("Environment variable $name is not set");
            }
            else
            {
                return $default;
            }
        }

        return $value;
    }

    public static function GetDebug(): bool
    {
        return (bool)self::Get("LU_DEBUG", "");
    }
}

