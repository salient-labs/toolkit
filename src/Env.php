<?php

declare(strict_types=1);

namespace Lkrms;

use DateTimeZone;
use Exception;
use RuntimeException;
use UnexpectedValueException;

/**
 * A minimal dotenv implementation with shell-compatible parsing
 *
 * @package Lkrms
 */
class Env
{
    private static $Loaded;

    private static $HonourTimezone = true;

    /**
     * Ignore the runtime environment's timezone
     *
     * Prevents {@see Env::Load()} using the environment variable `TZ` to set
     * the default timezone.
     *
     * @return void
     * @throws RuntimeException if {@see Env::Load()} has already been called
     */
    public static function IgnoreTimezone(): void
    {
        if (self::$Loaded)
        {
            throw new RuntimeException("Environment already loaded");
        }

        self::$HonourTimezone = false;
    }

    /**
     * Load environment variables
     *
     * Variables are loaded from the given .env file to `getenv()`, `$_ENV` and
     * `$_SERVER`.
     *
     * Each line in `$filename` should be a shell-compatible variable
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

            if (!trim($line) || substr($line, 0, 1) == "#")
            {
                continue;
            }
            elseif (!preg_match("/^([A-Z_][A-Z0-9_]*)=(\"(([^\"\$`]|\\\\[\"\$`])*)\"|'(([^']|'\\\\'')*)'|[^]\"\$'*?`\\s[]*)\$/i", $line, $match))
            {
                throw new UnexpectedValueException("Invalid entry at line $l in $filename");
            }

            $name = $match[1];

            if (!$replace && (getenv($name) !== false || array_key_exists($name, $_ENV) || array_key_exists($name, $_SERVER)))
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

        self::$Loaded = true;

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
     * and returns the first value it finds.
     *
     * @param string $name The environment variable to retrieve.
     * @param string|null $default The value to return if `$name` isn't set.
     * @return string
     * @throws RuntimeException if `$name` isn't set and no `$default` is given
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

    /**
     * Return true if debug mode is enabled
     *
     * Debug mode is enabled by setting `LU_DEBUG=1` in the environment.
     *
     * @return bool
     * @throws RuntimeException
     */
    public static function GetDebug(): bool
    {
        return (bool)self::Get("LU_DEBUG", "");
    }

    public static function GetMemoryLimit(): int
    {
        return Convert::SizeToBytes(ini_get('memory_limit') ?: 0);
    }

    public static function GetMemoryUsagePercent($precision = 2): float
    {
        $limit = self::GetMemoryLimit();

        if ($limit <= 0)
        {
            return 0;
        }
        else
        {
            return round(memory_get_usage(true) * 100 / $limit, $precision);
        }
    }
}

