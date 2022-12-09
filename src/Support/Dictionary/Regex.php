<?php declare(strict_types=1);

namespace Lkrms\Support\Dictionary;

use Lkrms\Concept\Dictionary;

/**
 * Useful PCRE regular expressions
 *
 */
final class Regex extends Dictionary
{
    /**
     * A valid PHP identifier, e.g. for variable names and classes
     *
     * @link https://www.php.net/manual/en/language.variables.basics.php
     * @link https://www.php.net/manual/en/language.oop5.basic.php
     */
    public const PHP_IDENTIFIER = '[[:alpha:]_\x80-\xff][[:alnum:]_\x80-\xff]*';

    /**
     * A valid PHP type, i.e. an optionally namespaced PHP_IDENTIFIER
     *
     * @see Regex::PHP_IDENTIFIER
     */
    public const PHP_TYPE = '(?:\\\\?' . self::PHP_IDENTIFIER . ')+';

    public static function delimit(string $regex, bool $anchor = false): string
    {
        return $anchor ? "/^$regex\$/" : "/$regex/";
    }
}
