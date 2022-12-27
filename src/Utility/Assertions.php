<?php declare(strict_types=1);

namespace Lkrms\Utility;

use RuntimeException;
use UnexpectedValueException;

/**
 * Throw an exception if a condition isn't met
 *
 */
final class Assertions
{
    private function throwUnexpectedValueException(string $message, ?string $name): void
    {
        throw new UnexpectedValueException(
            str_replace('{}', $name ? "'$name'" : 'value', $message)
        );
    }

    public function notEmpty($value, ?string $name = null): void
    {
        if (empty($value)) {
            $this->throwUnexpectedValueException('{} cannot be empty', $name);
        }
    }

    public function patternMatches(?string $value, string $pattern, ?string $name = null): void
    {
        if (is_null($value) || !preg_match($pattern, $value)) {
            $this->throwUnexpectedValueException("{} must match pattern '$pattern'", $name);
        }
    }

    public function sapiIsCli(): void
    {
        if (PHP_SAPI != 'cli') {
            throw new RuntimeException('CLI required');
        }
    }

    public function argvIsRegistered(): void
    {
        if (!ini_get('register_argc_argv')) {
            throw new RuntimeException('register_argc_argv is not enabled');
        }
    }

    public function localeIsUtf8(): void
    {
        if (($locale = setlocale(LC_CTYPE, '0')) === false) {
            throw new RuntimeException('Invalid locale settings');
        }

        if (!preg_match('/\.utf-?8$/i', $locale)) {
            throw new RuntimeException("'$locale' is not a UTF-8 locale");
        }
    }
}
