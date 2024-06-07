<?php declare(strict_types=1);

namespace Salient\Core\Utility;

use Salient\Core\Utility\Exception\InvalidEnvFileSyntaxException;
use Salient\Core\Utility\Exception\InvalidEnvironmentException;
use Closure;
use InvalidArgumentException;
use RuntimeException;

/**
 * Work with .env files and environment variables
 *
 * Non-empty lines in a `.env` file may contain either a shell-compatible
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
 * app_secret=''\''^K&4nnE'
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
 *
 * @api
 */
final class Env extends AbstractUtility
{
    /**
     * Set locale information from the environment
     *
     * Locale names are set from environment variables `LC_ALL`, `LC_COLLATE`,
     * `LC_CTYPE`, `LC_MONETARY`, `LC_NUMERIC`, `LC_TIME` and `LC_MESSAGES`, or
     * from `LANG`. On Windows, they are set from the system's language and
     * region settings.
     */
    public const APPLY_LOCALE = 1;

    /**
     * Set the default timezone used by date and time functions from the
     * environment
     *
     * If environment variable `TZ` contains a valid timezone, it is passed to
     * {@see date_default_timezone_set()}.
     */
    public const APPLY_TIMEZONE = 2;

    /**
     * Apply all recognised values from the environment to the running script
     */
    public const APPLY_ALL = Env::APPLY_LOCALE | Env::APPLY_TIMEZONE;

    /**
     * Load values from one or more .env files into the environment
     *
     * Values are applied to `$_ENV`, `$_SERVER` and {@see putenv()} unless
     * already present in one of them.
     *
     * Changes are applied after parsing all files. If a file contains invalid
     * syntax, an exception is thrown and the environment is not modified.
     *
     * Later values override earlier ones.
     *
     * @throws InvalidEnvFileSyntaxException if invalid syntax is found.
     */
    public static function loadFiles(string ...$filenames): void
    {
        $queue = [];
        $errors = [];
        foreach ($filenames as $filename) {
            $lines = explode("\n", Str::setEol(File::getContents($filename)));
            self::parseLines($lines, $queue, $errors, $filename);
        }

        if ($errors) {
            throw new InvalidEnvFileSyntaxException(Inflect::format(
                $filenames,
                'Unable to load .env {{#:file}}:%s',
                count($errors) === 1
                    ? ' ' . $errors[0]
                    : Format::list($errors, "\n- %s"),
            ));
        }

        foreach ($queue as $name => $value) {
            self::set($name, $value);
        }
    }

    /**
     * Apply values from the environment to the running script
     *
     * @param int-mask-of<Env::APPLY_*> $flags
     */
    public static function apply(int $flags = Env::APPLY_ALL): void
    {
        if ($flags & self::APPLY_LOCALE) {
            @setlocale(\LC_ALL, '');
        }

        if ($flags & self::APPLY_TIMEZONE) {
            $tz = Regex::replace(
                ['/^:?(.*\/zoneinfo\/)?/', '/^(UTC)0$/'],
                ['', '$1'],
                self::get('TZ', '')
            );
            if ($tz !== '') {
                $timezone = @timezone_open($tz);
                if ($timezone !== false) {
                    $tz = $timezone->getName();
                    date_default_timezone_set($tz);
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
        if ($value === false) {
            return self::_default($name, $default, false);
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
            return self::_default($name, $default, false);
        }
        if (!Regex::match('/^' . Regex::INTEGER_STRING . '$/', $value)) {
            throw new InvalidEnvironmentException(
                sprintf('Value is not an integer: %s', $name)
            );
        }
        return (int) $value;
    }

    /**
     * Get a boolean value from the environment
     *
     * @see Test::isBoolean()
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
            return self::_default($name, $default, -1);
        }
        if (trim($value) === '') {
            return false;
        }
        if (!Regex::match(
            '/^' . Regex::BOOLEAN_STRING . '$/',
            $value,
            $match,
            \PREG_UNMATCHED_AS_NULL
        )) {
            throw new InvalidEnvironmentException(
                sprintf('Value is not boolean: %s', $name)
            );
        }
        return $match['true'] === null ? false : true;
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
            throw new InvalidArgumentException('Invalid delimiter');
        }
        $value = self::_get($name);
        if ($value === false) {
            return self::_default($name, $default, false);
        }
        return $value === '' ? [] : explode($delimiter, $value);
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
            throw new InvalidArgumentException('Invalid delimiter');
        }
        $value = self::_get($name);
        if ($value === false) {
            return self::_default($name, $default, false);
        }
        if (trim($value) === '') {
            return [];
        }
        $regex = sprintf('/^%s(?:%s%1$s)*+$/', Regex::INTEGER_STRING, preg_quote($delimiter, '/'));
        if (!Regex::match($regex, $value)) {
            throw new InvalidEnvironmentException(
                sprintf('Value is not an integer list: %s', $name)
            );
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
        if ($value === false) {
            return self::_default($name, $default, false);
        }
        return $value === '' ? null : $value;
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
            return self::_default($name, $default, false);
        }
        if (trim($value) === '') {
            return null;
        }
        if (!Regex::match('/^' . Regex::INTEGER_STRING . '$/', $value)) {
            throw new InvalidEnvironmentException(
                sprintf('Value is not an integer: %s', $name)
            );
        }
        return (int) $value;
    }

    /**
     * Get a boolean value from the environment, returning null if it's empty
     *
     * @template T of bool|null|-1
     *
     * @param T|Closure(): T $default
     */
    public static function getNullableBool(string $name, $default = -1): ?bool
    {
        $value = self::_get($name);
        if ($value === false) {
            return self::_default($name, $default, -1);
        }
        if (trim($value) === '') {
            return null;
        }
        if (!Regex::match(
            '/^' . Regex::BOOLEAN_STRING . '$/',
            $value,
            $match,
            \PREG_UNMATCHED_AS_NULL
        )) {
            throw new InvalidEnvironmentException(
                sprintf('Value is not boolean: %s', $name)
            );
        }
        return $match['true'] === null ? false : true;
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
     * @template T of string[]|string|int[]|int|bool|null
     * @template TDefault of false|-1
     *
     * @param T|Closure(): T $default
     * @param TDefault $defaultDefault
     * @return (TDefault is false ? (T is false ? never : T) : (T is -1 ? never : T))
     */
    private static function _default(string $name, $default, $defaultDefault)
    {
        $default = Get::value($default);
        if ($default === $defaultDefault) {
            throw new InvalidEnvironmentException(
                sprintf('Value not found in environment: %s', $name)
            );
        }
        // @phpstan-ignore return.type
        return $default;
    }

    /**
     * Get the name of the current environment, e.g. "production" or
     * "development"
     *
     * Tries each of the following in turn and returns `null` if none are
     * present in the environment:
     *
     * - `app_env`
     * - `APP_ENV`
     * - `PHP_ENV`
     */
    public static function getEnvironment(): ?string
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
     * Check if dry-run mode is enabled in the environment
     */
    public static function getDryRun(): bool
    {
        return self::getFlag('DRY_RUN');
    }

    /**
     * Enable or disable dry-run mode in the environment
     */
    public static function setDryRun(bool $value): void
    {
        self::setFlag('DRY_RUN', $value);
    }

    /**
     * Check if debug mode is enabled in the environment
     */
    public static function getDebug(): bool
    {
        return self::getFlag('DEBUG');
    }

    /**
     * Enable or disable debug mode in the environment
     */
    public static function setDebug(bool $value): void
    {
        self::setFlag('DEBUG', $value);
    }

    /**
     * Check if a flag is enabled in the environment
     */
    public static function getFlag(string $name): bool
    {
        return self::getBool($name, false);
    }

    /**
     * Enable or disable a flag in the environment
     */
    public static function setFlag(string $name, bool $value): void
    {
        if (self::getBool($name, false) === $value) {
            return;
        }
        if ($value) {
            self::set($name, '1');
        } else {
            self::unset($name);
        }
    }

    /**
     * Get the current user's home directory from the environment
     */
    public static function getHomeDir(): ?string
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
     * @param-out array<string,string> $queue
     * @param string[] $errors
     * @param-out string[] $errors
     */
    private static function parseLines(
        array $lines,
        array &$queue,
        array &$errors,
        ?string $filename = null
    ): void {
        foreach ($lines as $i => $line) {
            if (trim($line) === '' || $line[0] === '#') {
                continue;
            }

            if (!Regex::match(<<<'REGEX'
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

            /** @var string */
            $name = $match['name'];
            if (
                array_key_exists($name, $_ENV)
                || array_key_exists($name, $_SERVER)
                || getenv($name) !== false
            ) {
                continue;
            }

            $double = $match['double'];
            if ($double !== null) {
                $queue[$name] = Regex::replace('/\\\\(["$\\\\`])/', '$1', $double);
                continue;
            }

            $single = $match['single'];
            if ($single !== null) {
                $queue[$name] = str_replace("'\''", "'", $single);
                continue;
            }

            /** @var string */
            $none = $match['none'];
            $queue[$name] = Regex::replace('/\\\\(.)/', '$1', $none);
        }
    }
}
