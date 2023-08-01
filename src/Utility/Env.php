<?php declare(strict_types=1);

namespace Lkrms\Utility;

use Lkrms\Exception\InvalidDotenvSyntaxException;
use Lkrms\Exception\InvalidEnvironmentException;
use Lkrms\Facade\Console;
use Lkrms\Support\Catalog\RegularExpression as Regex;
use Lkrms\Utility\Catalog\EnvFlag;
use Lkrms\Utility\Convert;
use LogicException;
use RuntimeException;

/**
 * Work with .env files and environment variables
 *
 * Non-empty lines in `.env` files may contain either a shell-compatible
 * variable assignment (see below for limitations) or a comment.
 *
 * Example:
 *
 * ```shell
 * LANG=en_US.UTF-8
 * TZ=Australia/Sydney
 *
 * # app_secret is parsed as: '^K&4nnE
 * app_client_id=d8f024b9-1dfb-4dde-8f29-db98eefa317c
 * app_secret='\\''^K&4nnE'
 * ```
 *
 * - Unquoted values cannot contain unescaped whitespace, `"`, `'`, `$`,
 *   backticks, or glob characters (`*`, `?`, `[`, `]`).
 * - Quoted values must be fully enclosed by one pair of single or double
 *   quotes.
 * - Double-quoted values cannot contain `"`, `$`, or backticks unless they are
 *   escaped.
 * - Single-quoted values may contain single quotes if this syntax is used:
 *   `'\''`
 * - Variable expansion and command substitution are not supported.
 * - Comment lines must start with `#`.
 */
final class Env
{
    /**
     * Load one or more .env files
     *
     * Values are applied to `$_ENV`, `$_SERVER` and `putenv()` unless already
     * present in the environment.
     *
     * Changes are applied after parsing all files in the given order. If a file
     * contains invalid syntax, an exception is thrown and no changes are
     * applied.
     *
     * Later values override earlier ones.
     *
     * @throws InvalidDotenvSyntaxException if invalid syntax is found.
     */
    public static function load(string ...$path): void
    {
        $queue = [];
        $errors = [];
        foreach ($path as $filename) {
            $lines = explode("\n", Convert::lineEndingsToUnix(file_get_contents($filename)));
            self::parse($lines, $queue, $errors, $filename);
        }
        self::doLoad($queue, $errors);
    }

    /**
     * @param string[] $lines
     * @param array<string,string> $queue
     * @param string[] $errors
     * @param string|null $filename
     */
    private static function parse(array $lines, array &$queue, array &$errors, ?string $filename = null): void
    {
        foreach ($lines as $i => $line) {
            if (!trim($line) || $line[0] === '#') {
                continue;
            }
            if (!preg_match(<<<'REGEX'
                    / ^
                    (?<name> [a-z_] [a-z0-9_]*+ ) = (?:
                    " (?<double> (?: [^"$\\`]++ | \\ ["$\\`] | \\ )*+ ) " |
                    ' (?<single> (?: [^']++            | ' \\ ' ' )*+ ) ' |
                      (?<none>   (?: [^]"$'*?\\`\s[]++     | \\ . )*+ )
                    ) $ /xi
                    REGEX, $line, $match, PREG_UNMATCHED_AS_NULL)) {
                $errors[] = $filename === null
                    ? sprintf('invalid syntax at index %d', $i)
                    : sprintf('invalid syntax at %s:%d', $filename, $i + 1);
                continue;
            }
            $name = $match['name'];
            if (array_key_exists($name, $_ENV) ||
                    array_key_exists($name, $_SERVER) ||
                    getenv($name) !== false) {
                continue;
            }
            if (($double = $match['double']) !== null) {
                $queue[$name] = preg_replace('/\\\\(["$\\\\`])/', '\1', $double);
                continue;
            }
            if (($single = $match['single']) !== null) {
                $queue[$name] = str_replace("'\\''", "'", $single);
                continue;
            }
            $queue[$name] = preg_replace('/\\\\(.)/', '\1', $match['none']);
        }
    }

    /**
     * @param array<string,string> $queue
     * @param string[] $errors
     */
    private static function doLoad(array $queue, array $errors): void
    {
        if ($errors) {
            throw (new InvalidDotenvSyntaxException('Unable to load .env files', ...$errors))
                ->reportErrors();
        }
        foreach ($queue as $name => $value) {
            self::set($name, $value);
        }
    }

    /**
     * Apply values from the environment to the running script
     *
     * @param int-mask-of<EnvFlag::*> $flags
     */
    public static function apply(int $flags = EnvFlag::ALL): void
    {
        if ($flags & EnvFlag::LOCALE) {
            if (($locale = setlocale(LC_ALL, '')) === false) {
                throw new InvalidEnvironmentException('Unable to set locale from environment');
            }
            Console::debug('Locale:', $locale);
        }

        if ($flags & EnvFlag::TIMEZONE &&
                ($tz = preg_replace('/^:?(.*\/zoneinfo\/)?/', '', self::get('TZ', '')))) {
            if (($timezone = timezone_open($tz)) === false) {
                Console::debug('Invalid timezone:', $tz);
            } else {
                date_default_timezone_set($tz = $timezone->getName());
                Console::debug('Timezone:', $tz);
            }
        }
    }

    /**
     * Set an environment variable
     *
     * The value is applied to `$_ENV`, `$_SERVER` and `putenv()`.
     *
     */
    public static function set(string $name, string $value): void
    {
        if (putenv($name . '=' . $value) === false) {
            throw new RuntimeException(sprintf('Unable to set environment variable: %s', $name));
        }
        $_ENV[$name] = $value;
        $_SERVER[$name] = $value;
    }

    /**
     * Unset an environment variable
     *
     * The change is applied to `$_ENV`, `$_SERVER` and `putenv()`.
     *
     */
    public static function unset(string $name): void
    {
        if (putenv($name) === false) {
            throw new RuntimeException(sprintf('Unable to unset environment variable: %s', $name));
        }
        unset($_ENV[$name]);
        unset($_SERVER[$name]);
    }

    /**
     * @return string|false
     */
    private static function _get(string $name, bool $assertValueIsString = true)
    {
        if (array_key_exists($name, $_ENV)) {
            $value = $_ENV[$name];
        } elseif (array_key_exists($name, $_SERVER)) {
            $value = $_SERVER[$name];
        } else {
            return ($value = getenv($name, true)) === false
                ? getenv($name)
                : $value;
        }
        if ($assertValueIsString && !is_string($value)) {
            throw new InvalidEnvironmentException(sprintf('Environment variable is not a string: %s', $name));
        }
        return $value;
    }

    /**
     * True if a variable is present in the environment
     *
     */
    public static function has(string $name): bool
    {
        return self::_get($name, false) !== false;
    }

    /**
     * Get a value from the environment
     *
     * Checks `$_ENV`, `$_SERVER` and `getenv()` for the variable and returns
     * the first value found.
     *
     * If the variable is not found, `$default` is returned if given, otherwise
     * an {@see InvalidEnvironmentException} is thrown.
     *
     * @template T of string|null
     * @param T $default
     * @return T|string
     * @throws InvalidEnvironmentException if `$name` is not present in the
     * environment and `$default` is not given.
     */
    public static function get(string $name, ?string $default = null): ?string
    {
        $value = self::_get($name);
        if ($value === false) {
            if (func_num_args() < 2) {
                self::throwValueNotFoundException($name);
            }
            return $default;
        }
        return $value;
    }

    /**
     * Get an integer value from the environment
     *
     * Checks `$_ENV`, `$_SERVER` and `getenv()` for the variable and returns
     * the first value found.
     *
     * If the value is not an integer, an {@see InvalidEnvironmentException} is
     * thrown.
     *
     * If the variable is not found, `$default` is returned if given, otherwise
     * an {@see InvalidEnvironmentException} is thrown.
     *
     * @template T of int|null
     * @param T $default
     * @return T|int
     * @throws InvalidEnvironmentException if `$name` is not present in the
     * environment and `$default` is not given, or if the value of `$name` is
     * not an integer.
     */
    public static function getInt(string $name, ?int $default = null): ?int
    {
        $value = self::_get($name);
        if ($value === false) {
            if (func_num_args() < 2) {
                self::throwValueNotFoundException($name);
            }
            return $default;
        }
        if (!preg_match('/^[0-9]+$/', $value)) {
            throw new InvalidEnvironmentException(sprintf('Value is not an integer: %s', $name));
        }
        return (int) $value;
    }

    /**
     * Get a boolean value from the environment
     *
     * Checks `$_ENV`, `$_SERVER` and `getenv()` for the variable and returns
     * the first value found.
     *
     * If the value is not boolean, an {@see InvalidEnvironmentException} is
     * thrown.
     *
     * If the variable is not found, `$default` is returned if given, otherwise
     * an {@see InvalidEnvironmentException} is thrown.
     *
     * - Values equivalent to `false`: `""`, `"0"`, `"n"`, `"no"`, `"off"`,
     *   `"f"`, `"false"`, `"disable"` `"disabled"`
     * - Values equivalent to `true`: `"1"`, `"y"`, `"yes"`, `"on"`, `"t"`,
     *   `"true"`, `"enable"`, `"enabled"`
     *
     * @template T of bool|null
     * @param T $default
     * @return T|bool
     * @throws InvalidEnvironmentException if `$name` is not present in the
     * environment and `$default` is not given, or if the value of `$name` is
     * not boolean.
     */
    public static function getBool(string $name, ?bool $default = null): ?bool
    {
        $value = self::_get($name);
        if ($value === false) {
            if (func_num_args() < 2) {
                self::throwValueNotFoundException($name);
            }
            return $default;
        }
        if ($value === '') {
            return false;
        }
        if (!preg_match(
            Regex::anchorAndDelimit(Regex::BOOLEAN_STRING),
            $value,
            $match,
            PREG_UNMATCHED_AS_NULL
        )) {
            throw new InvalidEnvironmentException(sprintf('Value is not boolean: %s', $name));
        }
        return $match['true'] ? true : false;
    }

    /**
     * Get a list of strings from the environment
     *
     * Returns `$default` if `$name` is not set or an empty array if it's empty,
     * otherwise splits its value on `$delimiter` before returning.
     *
     * @template T of string[]|null
     * @param T $default
     * @return T|string[]
     * @throws InvalidEnvironmentException if `$name` is not present in the
     * environment and `$default` is not given.
     */
    public static function getList(string $name, ?array $default = null, string $delimiter = ','): ?array
    {
        if (!$delimiter) {
            throw new LogicException('Invalid delimiter');
        }
        $value = self::_get($name);
        if ($value === false) {
            if (func_num_args() < 2) {
                self::throwValueNotFoundException($name);
            }
            return $default;
        }
        return $value !== '' ? explode($delimiter, $value) : [];
    }

    /**
     * Get a list of integers from the environment
     *
     * Returns `$default` if `$name` is not set or an empty array if it's empty,
     * otherwise splits its value on `$delimiter` and casts entries to integers
     * before returning.
     *
     * @template T of int[]|null
     * @param T $default
     * @return T|int[]
     * @throws InvalidEnvironmentException if `$name` is not present in the
     * environment and `$default` is not given, or if the value of `$name` is
     * invalid.
     */
    public static function getIntList(string $name, ?array $default = null, string $delimiter = ','): ?array
    {
        if (!$delimiter) {
            throw new LogicException('Invalid delimiter');
        }
        $value = self::_get($name);
        if ($value === false) {
            if (func_num_args() < 2) {
                self::throwValueNotFoundException($name);
            }
            return $default;
        }
        if ($value === '') {
            return [];
        };
        $sep = preg_quote($delimiter, '/');
        if (!preg_match("/^[0-9]++(?:{$sep}[0-9]++)*+$/", $value)) {
            throw new InvalidEnvironmentException(sprintf('Value is not an integer list: %s', $name));
        }
        $list = [];
        foreach (explode($delimiter, $value) as $value) {
            $list[] = (int) $value;
        }
        return $list;
    }

    /**
     * Get a value from the environment, returning null if it's empty
     *
     * Checks `$_ENV`, `$_SERVER` and `getenv()` for the variable and returns
     * the first value found.
     *
     * If the variable is not found, `$default` is returned if given, otherwise
     * an {@see InvalidEnvironmentException} is thrown.
     *
     * @throws InvalidEnvironmentException if `$name` is not present in the
     * environment and `$default` is not given.
     */
    public static function getNullable(string $name, ?string $default = null): ?string
    {
        $value = self::_get($name);
        if ($value === false) {
            if (func_num_args() < 2) {
                self::throwValueNotFoundException($name);
            }
            return $default;
        }
        return trim($value) === '' ? null : $value;
    }

    /**
     * Get an integer value from the environment, returning null if it's empty
     *
     * Checks `$_ENV`, `$_SERVER` and `getenv()` for the variable and returns
     * the first value found.
     *
     * If the value is not empty and not an integer, an
     * {@see InvalidEnvironmentException} is thrown.
     *
     * If the variable is not found, `$default` is returned if given, otherwise
     * an {@see InvalidEnvironmentException} is thrown.
     *
     * @throws InvalidEnvironmentException if `$name` is not present in the
     * environment and `$default` is not given, or if the value of `$name` is
     * neither an integer nor an empty string.
     */
    public static function getNullableInt(string $name, ?int $default = null): ?int
    {
        $value = self::_get($name);
        if ($value === false) {
            if (func_num_args() < 2) {
                self::throwValueNotFoundException($name);
            }
            return $default;
        }
        if ($value === '') {
            return null;
        }
        if (!preg_match('/^[0-9]+$/', $value)) {
            throw new InvalidEnvironmentException(sprintf('Value is not an integer: %s', $name));
        }
        return (int) $value;
    }

    /**
     * Get a boolean value from the environment, returning null if it's empty
     *
     * Checks `$_ENV`, `$_SERVER` and `getenv()` for the variable and returns
     * the first value found.
     *
     * If the value is not empty and not boolean, an
     * {@see InvalidEnvironmentException} is thrown.
     *
     * If the variable is not found, `$default` is returned if given, otherwise
     * an {@see InvalidEnvironmentException} is thrown.
     *
     * - Values equivalent to `false`: `"0"`, `"n"`, `"no"`, `"off"`, `"f"`,
     *   `"false"`, `"disable"` `"disabled"`
     * - Values equivalent to `true`: `"1"`, `"y"`, `"yes"`, `"on"`, `"t"`,
     *   `"true"`, `"enable"`, `"enabled"`
     *
     * @throws InvalidEnvironmentException if `$name` is not present in the
     * environment and `$default` is not given, or if the value of `$name` is
     * neither boolean nor an empty string.
     */
    public static function getNullableBool(string $name, ?bool $default = null): ?bool
    {
        $value = self::_get($name);
        if ($value === false) {
            if (func_num_args() < 2) {
                self::throwValueNotFoundException($name);
            }
            return $default;
        }
        if ($value === '') {
            return null;
        }
        if (!preg_match(
            Regex::anchorAndDelimit(Regex::BOOLEAN_STRING),
            $value,
            $match,
            PREG_UNMATCHED_AS_NULL
        )) {
            throw new InvalidEnvironmentException(sprintf('Value is not boolean: %s', $name));
        }
        return $match['true'] ? true : false;
    }

    private static function getOrSetBool(string $name, ?bool $newState = null): bool
    {
        if (func_num_args() > 1 && !is_null($newState)) {
            if ($newState) {
                self::set($name, '1');
            } else {
                self::unset($name);
            }
        }

        return (bool) self::get($name, '');
    }

    /**
     * Optionally turn dry-run mode on or off, then return its current state
     *
     * Dry-run mode can also be enabled by setting the `DRY_RUN` environment
     * variable.
     *
     */
    public static function dryRun(?bool $newState = null): bool
    {
        return self::getOrSetBool('DRY_RUN', ...func_get_args());
    }

    /**
     * Optionally turn debug mode on or off, then return its current state
     *
     * Debug mode can also be enabled by setting the `DEBUG` environment
     * variable.
     *
     */
    public static function debug(?bool $newState = null): bool
    {
        return self::getOrSetBool('DEBUG', ...func_get_args());
    }

    /**
     * True if the current locale for character classification and conversion
     * (LC_CTYPE) supports UTF-8
     *
     */
    public static function isLocaleUtf8(): bool
    {
        if (($locale = setlocale(LC_CTYPE, '0')) === false) {
            Console::warnOnce('Invalid locale settings');

            return false;
        }

        return (bool) preg_match('/\.utf-?8$/i', $locale);
    }

    /**
     * Get the current user's home directory from the environment
     *
     */
    public static function home(): ?string
    {
        if ($home = self::get('HOME', null)) {
            return $home;
        }
        if (($homeDrive = self::get('HOMEDRIVE', null)) &&
                ($homePath = self::get('HOMEPATH', null))) {
            return $homeDrive . $homePath;
        }

        return null;
    }

    /**
     * @return never
     */
    private static function throwValueNotFoundException(string $name)
    {
        throw new InvalidEnvironmentException(sprintf('Value not found in environment: %s', $name));
    }
}
