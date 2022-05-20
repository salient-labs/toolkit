<?php

declare(strict_types=1);

namespace Lkrms\Util;

use DateTimeZone;
use Exception;
use Lkrms\Console\Console;
use RuntimeException;
use UnexpectedValueException;

/**
 * A minimal dotenv implementation with shell-compatible parsing
 *
 */
abstract class Env
{
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
     * @param string $filename The `.env` file to load.
     * @param bool $replace If `true`, override existing environment variables.
     * @throws RuntimeException if `$filename` cannot be opened
     * @throws UnexpectedValueException if `$filename` cannot be parsed
     */
    public static function load(string $filename, bool $replace = false): void
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
                $value = preg_replace("/\\\\([\"\$\\`])/", "\\1", $match[3]);
            }
            elseif ($match[5] ?? null)
            {
                $value = str_replace("'\\''", "'", $match[5]);
            }
            else
            {
                $value = $match[2];
            }

            self::set($name, $value);
        }

        self::apply();
    }

    /**
     * Apply values from the environment to the running script
     *
     * Specifically:
     * - If `TZ` is a valid timezone, pass it to `date_default_timezone_set`.
     */
    public static function apply(): void
    {
        if ($tz = preg_replace("/^:?(.*\/zoneinfo\/)?/", "", self::get("TZ", "")))
        {
            try
            {
                $timezone = new DateTimeZone($tz);
                date_default_timezone_set($timezone->getName());
            }
            catch (Exception $ex)
            {
                Console::debug("Not a valid timezone:", $tz, $ex);
            }
        }
    }

    /**
     * Set an environment variable
     *
     * The variable is loaded to `getenv()`, `$_ENV` and `$_SERVER`.
     *
     * @param string $name
     * @param string $value
     */
    public static function set(string $name, string $value): void
    {
        putenv($name . "=" . $value);
        $_ENV[$name]    = $value;
        $_SERVER[$name] = $value;
    }

    /**
     * Unset an environment variable
     *
     * The variable is removed from `getenv()`, `$_ENV` and `$_SERVER`.
     *
     * @param string $name
     */
    public static function unset(string $name): void
    {
        putenv($name);
        unset($_ENV[$name]);
        unset($_SERVER[$name]);
    }

    /**
     * Retrieve an environment variable
     *
     * Looks for `$name` in `$_ENV`, `$_SERVER` and `getenv()`, in that order,
     * and returns the first value it finds.
     *
     * @param string $name The environment variable to retrieve.
     * @param string|null $default The value to return if `$name` is not set.
     * @return null|string
     * @throws RuntimeException if `$name` is not set and no `$default` is given
     */
    public static function get(string $name, string $default = null): ?string
    {
        $value = $_ENV[$name] ?? $_SERVER[$name] ?? (($local = getenv($name, true)) !== false ? $local : getenv($name));

        if ($value === false)
        {
            if (func_num_args() < 2)
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
     * Return an environment variable as a list of strings
     *
     * See {@see Env::get()} for details.
     *
     * @param string $name The environment variable to retrieve.
     * @param string[]|null $default The value to return if `$name` is not set.
     * @param string $delimiter The character used between items.
     * @return string[]|null
     * @throws RuntimeException if `$name` is not set and no `$default` is given
     */
    public static function getList(string $name, array $default = null, string $delimiter = ","): ?array
    {
        if (!$delimiter)
        {
            throw new UnexpectedValueException("Invalid delimiter");
        }

        if (func_num_args() < 2)
        {
            $value = self::get($name);
        }
        else
        {
            $value = self::get($name, null);

            if (is_null($value))
            {
                return $default;
            }
        }

        return $value ? explode($delimiter, $value) : [];
    }

    private static function getOrSetBool(string $name, bool $newState = null): bool
    {
        if (func_num_args() > 1 && !is_null($newState))
        {
            if ($newState)
            {
                self::set($name, "1");
            }
            else
            {
                self::unset($name);
            }
        }

        return (bool)self::get($name, "");
    }

    /**
     * Optionally turn dry-run mode on or off, then return its current state
     *
     * Dry-run mode can also be enabled by setting the `DRY_RUN` environment
     * variable.
     *
     * @param bool|null $newState
     * @return bool
     */
    public static function dryRun(bool $newState = null): bool
    {
        return self::getOrSetBool("DRY_RUN", ...func_get_args());
    }

    /**
     * Optionally turn debug mode on or off, then return its current state
     *
     * Debug mode can also be enabled by setting the `DEBUG` environment
     * variable.
     *
     * @param bool|null $newState
     * @return bool
     */
    public static function debug(bool $newState = null): bool
    {
        return self::getOrSetBool("DEBUG", ...func_get_args());
    }
}
