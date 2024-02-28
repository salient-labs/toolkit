<?php declare(strict_types=1);

namespace Salient\Core\Utility;

use Salient\Catalog\Core\EnvFlag;
use Salient\Catalog\Core\Regex;
use Salient\Core\Exception\InvalidDotEnvSyntaxException;
use Salient\Core\Exception\InvalidEnvironmentException;
use Salient\Core\Exception\RuntimeException;
use Salient\Core\Facade\Console;
use Salient\Core\AbstractUtility;
use Closure;
use LogicException;

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
 *
 * {@see Env::get()}, {@see Env::getInt()}, etc. check `$_ENV`, `$_SERVER` and
 * {@see getenv()} for a given variable and return the first value found. If the
 * value is not of the expected type, an {@see InvalidEnvironmentException} is
 * thrown. If the variable is not present in the environment, `$default` is
 * returned if given, otherwise an {@see InvalidEnvironmentException} is thrown.
 */
final class Env extends AbstractUtility
{
    /**
     * Load one or more .env files
     *
     * Values are applied to `$_ENV`, `$_SERVER` and {@see putenv()} unless
     * already present.
     *
     * Changes are applied after parsing all files in the given order. If a file
     * contains invalid syntax, an exception is thrown and the environment is
     * not modified.
     *
     * Later values override earlier ones.
     *
     * @throws InvalidDotEnvSyntaxException if invalid syntax is found.
     */
    public static function load(string ...$path): void
    {
        $queue = [];
        $errors = [];
        foreach ($path as $filename) {
            $lines = explode("\n", Str::setEol(File::getContents($filename)));
            self::parse($lines, $queue, $errors, $filename);
        }
        self::doLoad($queue, $errors);
    }

    /**
     * Apply values from the environment to the running script
     *
     * @param int-mask-of<EnvFlag::*> $flags
     */
    public static function apply(int $flags = EnvFlag::ALL): void
    {
        if ($flags & EnvFlag::LOCALE) {
            $locale = @setlocale(\LC_ALL, '');
            if ($locale === false) {
                Console::debug('Unable to set locale from environment');
            } else {
                Console::debug('Locale:', $locale);
            }
        }

        if ($flags & EnvFlag::TIMEZONE) {
            $tz = Pcre::replace(
                ['/^:?(.*\/zoneinfo\/)?/', '/^(UTC)0$/'],
                ['', '$1'],
                self::get('TZ', '')
            );
            if ($tz !== '') {
                $timezone = @timezone_open($tz);
                if ($timezone === false) {
                    Console::debug('Invalid timezone:', $tz);
                } else {
                    $tz = $timezone->getName();
                    date_default_timezone_set($tz);
                    Console::debug('Timezone:', $tz);
                }
            }
        }
    }

    /**
     * Set an environment variable
     *
     * The value is applied to `$_ENV`, `$_SERVER` and {@see putenv()}.
     */
    public static function set(string $name, string $value): void
    {
        if (putenv($name . '=' . $value) === false) {
            // @codeCoverageIgnoreStart
            throw new RuntimeException(sprintf(
                'Unable to set environment variable: %s',
                $name,
            ));
            // @codeCoverageIgnoreEnd
        }
        $_ENV[$name] = $value;
        $_SERVER[$name] = $value;
    }

    /**
     * Unset an environment variable
     *
     * The variable is removed from `$_ENV`, `$_SERVER` and {@see putenv()}.
     */
    public static function unset(string $name): void
    {
        if (putenv($name) === false) {
            // @codeCoverageIgnoreStart
            throw new RuntimeException(sprintf(
                'Unable to unset environment variable: %s',
                $name,
            ));
            // @codeCoverageIgnoreEnd
        }
        unset($_ENV[$name]);
        unset($_SERVER[$name]);
    }

    /**
     * Check if a variable is present in the environment
     */
    public static function has(string $name): bool
    {
        return self::_get($name, false) !== false;
    }

    /**
     * Get a value from the environment
     *
     * @template T of string|null|false
     *
     * @param T|Closure(): T $default
     * @return (T is string ? string : (T is null ? string|null : string|never))
     */
    public static function get(string $name, $default = false): ?string
    {
        $value = self::_get($name);

        if ($value !== false) {
            return $value;
        }

        $default = Get::value($default);
        if ($default === false) {
            self::throwValueNotFoundException($name);
        }

        return $default;
    }

    /**
     * Get a class name from the environment
     *
     * @template TClass of object
     * @template T of class-string<TClass>|null|false
     *
     * @param class-string<TClass> $class
     * @param T|Closure(): T $default
     * @return (T is class-string<TClass> ? class-string<TClass> : (T is null ? class-string<TClass>|null : class-string<TClass>|never))
     */
    public static function getClass(string $name, string $class, $default = false)
    {
        $value = self::_get($name);

        if ($value === false) {
            $default = Get::value($default);
            if ($default === false) {
                self::throwValueNotFoundException($name);
            }

            return $default;
        }

        if (!is_a($value, $class, true)) {
            throw new InvalidEnvironmentException(sprintf(
                '%s does not inherit %s',
                $value,
                $class,
            ));
        }

        return $value;
    }

    /**
     * Get an integer value from the environment
     *
     * @template T of int|null|false
     *
     * @param T|Closure(): T $default
     * @return (T is int ? int : (T is null ? int|null : int|never))
     */
    public static function getInt(string $name, $default = false): ?int
    {
        $value = self::_get($name);

        if ($value === false) {
            $default = Get::value($default);
            if ($default === false) {
                self::throwValueNotFoundException($name);
            }

            return $default;
        }

        if (!Pcre::match('/^' . Regex::INTEGER_STRING . '$/', $value)) {
            throw new InvalidEnvironmentException(sprintf(
                'Value is not an integer: %s',
                $name,
            ));
        }

        return (int) $value;
    }

    /**
     * Get a boolean value from the environment
     *
     * @see Test::isBoolValue()
     *
     * @template T of bool|null|-1
     *
     * @param T|Closure(): T $default
     * @return (T is bool ? bool : (T is null ? bool|null : bool|never))
     */
    public static function getBool(string $name, $default = -1): ?bool
    {
        $value = self::_get($name);

        if ($value === false) {
            $default = Get::value($default);
            if ($default === -1) {
                self::throwValueNotFoundException($name);
            }

            return $default;
        }

        if (trim($value) === '') {
            return false;
        }

        if (!Pcre::match(
            '/^' . Regex::BOOLEAN_STRING . '$/',
            $value,
            $match,
            \PREG_UNMATCHED_AS_NULL
        )) {
            throw new InvalidEnvironmentException(sprintf(
                'Value is not boolean: %s',
                $name,
            ));
        }

        return $match['true'] !== null ? true : false;
    }

    /**
     * Get a list of values from the environment
     *
     * @template T of string[]|null|false
     *
     * @param T|Closure(): T $default
     * @return (T is string[] ? string[] : (T is null ? string[]|null : string[]|never))
     */
    public static function getList(string $name, $default = false, string $delimiter = ','): ?array
    {
        if ($delimiter === '') {
            throw new LogicException('Invalid delimiter');
        }

        $value = self::_get($name);

        if ($value !== false) {
            return $value === '' ? [] : explode($delimiter, $value);
        }

        $default = Get::value($default);
        if ($default === false) {
            self::throwValueNotFoundException($name);
        }

        return $default;
    }

    /**
     * Get a list of integers from the environment
     *
     * @template T of int[]|null|false
     *
     * @param T|Closure(): T $default
     * @return (T is int[] ? int[] : (T is null ? int[]|null : int[]|never))
     */
    public static function getIntList(string $name, $default = false, string $delimiter = ','): ?array
    {
        if ($delimiter === '') {
            throw new LogicException('Invalid delimiter');
        }

        $value = self::_get($name);

        if ($value === false) {
            $default = Get::value($default);
            if ($default === false) {
                self::throwValueNotFoundException($name);
            }

            return $default;
        }

        if (trim($value) === '') {
            return [];
        }

        $regex = sprintf('/^%s(?:%s%1$s)*+$/', Regex::INTEGER_STRING, preg_quote($delimiter, '/'));
        if (!Pcre::match($regex, $value)) {
            throw new InvalidEnvironmentException(sprintf(
                'Value is not an integer list: %s',
                $name,
            ));
        }

        foreach (explode($delimiter, $value) as $value) {
            $list[] = (int) $value;
        }

        return $list;
    }

    /**
     * Get a value from the environment, returning null if it's empty
     *
     * @template T of string|null|false
     *
     * @param T|Closure(): T $default
     */
    public static function getNullable(string $name, $default = false): ?string
    {
        $value = self::_get($name);

        if ($value !== false) {
            return $value === '' ? null : $value;
        }

        $default = Get::value($default);
        if ($default === false) {
            self::throwValueNotFoundException($name);
        }

        return $default;
    }

    /**
     * Get an integer value from the environment, returning null if it's empty
     *
     * @template T of int|null|false
     *
     * @param T|Closure(): T $default
     */
    public static function getNullableInt(string $name, $default = false): ?int
    {
        $value = self::_get($name);

        if ($value === false) {
            if ($default === false) {
                self::throwValueNotFoundException($name);
            }

            $default = Get::value($default);
            return $default;
        }

        if (trim($value) === '') {
            return null;
        }

        if (!Pcre::match('/^' . Regex::INTEGER_STRING . '$/', $value)) {
            throw new InvalidEnvironmentException(sprintf(
                'Value is not an integer: %s',
                $name,
            ));
        }

        return (int) $value;
    }

    /**
     * Get a boolean value from the environment, returning null if it's empty
     *
     * @see Test::isBoolValue()
     *
     * @template T of bool|null|-1
     *
     * @param T|Closure(): T $default
     */
    public static function getNullableBool(string $name, $default = -1): ?bool
    {
        $value = self::_get($name);

        if ($value === false) {
            $default = Get::value($default);
            if ($default === -1) {
                self::throwValueNotFoundException($name);
            }

            return $default;
        }

        if (trim($value) === '') {
            return null;
        }

        if (!Pcre::match(
            '/^' . Regex::BOOLEAN_STRING . '$/',
            $value,
            $match,
            \PREG_UNMATCHED_AS_NULL
        )) {
            throw new InvalidEnvironmentException(sprintf(
                'Value is not boolean: %s',
                $name,
            ));
        }

        return $match['true'] !== null ? true : false;
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
            $value = getenv($name, true);
            return $value === false ? getenv($name) : $value;
        }
        if ($assertValueIsString && !is_string($value)) {
            throw new InvalidEnvironmentException(sprintf(
                'Value is not a string: %s',
                $name,
            ));
        }
        return $value;
    }

    /**
     * Get the name of the current environment, e.g. "production" or
     * "development"
     *
     * Tries each of the following variables in turn and returns `null` if none
     * are present in the environment:
     *
     * - `app_env`
     * - `APP_ENV`
     * - `PHP_ENV`
     */
    public static function environment(): ?string
    {
        return self::getNullable(
            'app_env',
            fn() => self::getNullable(
                'APP_ENV',
                fn() => self::getNullable('PHP_ENV', null)
            )
        );
    }

    /**
     * Get and optionally set the state of dry-run mode
     *
     * Dry-run mode is enabled by setting the `DRY_RUN` environment variable.
     */
    public static function dryRun(?bool $enable = null): bool
    {
        return self::flag('DRY_RUN', $enable);
    }

    /**
     * Get and optionally set the state of debug mode
     *
     * Debug mode is enabled by setting the `DEBUG` environment variable.
     */
    public static function debug(?bool $enable = null): bool
    {
        return self::flag('DEBUG', $enable);
    }

    /**
     * Get and optionally set the state of a boolean value in the environment
     */
    public static function flag(string $name, ?bool $enable = null): bool
    {
        $state = self::getBool($name, false);
        if ($enable === null || $enable === $state) {
            return $state;
        }

        if ($enable) {
            self::set($name, '1');
        } else {
            self::unset($name);
        }

        return $enable;
    }

    /**
     * True if the current locale for character classification and conversion
     * (LC_CTYPE) supports UTF-8
     */
    public static function isLocaleUtf8(): bool
    {
        $locale = @setlocale(\LC_CTYPE, '0');
        if ($locale === false) {
            Console::warnOnce('Invalid locale settings');
            return false;
        }

        return (bool) Pcre::match('/\.utf-?8$/i', $locale);
    }

    /**
     * Get the current user's home directory from the environment
     */
    public static function home(): ?string
    {
        $home = self::get('HOME', null);
        if ($home !== null) {
            return $home;
        }

        $homeDrive = self::get('HOMEDRIVE', null);
        $homePath = self::get('HOMEPATH', null);
        if ($homeDrive !== null && $homePath !== null) {
            return $homeDrive . $homePath;
        }

        return null;
    }

    /**
     * @param string[] $lines
     * @param array<string,string> $queue
     * @param string[] $errors
     */
    private static function parse(array $lines, array &$queue, array &$errors, ?string $filename = null): void
    {
        foreach ($lines as $i => $line) {
            if (trim($line) === '' || $line[0] === '#') {
                continue;
            }

            if (!Pcre::match(<<<'REGEX'
                    / ^
                    (?<name> [a-z_] [a-z0-9_]*+ ) = (?:
                    " (?<double> (?: [^"$\\`]++ | \\ ["$\\`] | \\ )*+ ) " |
                    ' (?<single> (?: [^']++            | ' \\ ' ' )*+ ) ' |
                      (?<none>   (?: [^]"$'*?\\`\s[]++     | \\ . )*+ )
                    ) $ /xi
                    REGEX, $line, $match, \PREG_UNMATCHED_AS_NULL)) {
                $errors[] = $filename === null
                    ? sprintf('invalid syntax at index %d', $i)
                    : sprintf('invalid syntax at %s:%d', $filename, $i + 1);
                continue;
            }

            $name = $match['name'];
            if (
                array_key_exists($name, $_ENV) ||
                array_key_exists($name, $_SERVER) ||
                getenv($name) !== false
            ) {
                continue;
            }

            /** @var string|null */
            $double = $match['double'];
            if ($double !== null) {
                $queue[$name] = Pcre::replace('/\\\\(["$\\\\`])/', '$1', $double);
                continue;
            }

            /** @var string|null */
            $single = $match['single'];
            if ($single !== null) {
                $queue[$name] = str_replace("'\''", "'", $single);
                continue;
            }

            /** @var string */
            $none = $match['none'];
            $queue[$name] = Pcre::replace('/\\\\(.)/', '$1', $none);
        }
    }

    /**
     * @param array<string,string> $queue
     * @param string[] $errors
     */
    private static function doLoad(array $queue, array $errors): void
    {
        if ($errors) {
            throw (new InvalidDotEnvSyntaxException('Unable to load .env files', ...$errors))
                ->reportErrors();
        }

        foreach ($queue as $name => $value) {
            self::set($name, $value);
        }
    }

    /**
     * @return never
     */
    private static function throwValueNotFoundException(string $name)
    {
        throw new InvalidEnvironmentException(sprintf(
            'Value not found in environment: %s',
            $name,
        ));
    }
}
