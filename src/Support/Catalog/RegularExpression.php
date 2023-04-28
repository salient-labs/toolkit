<?php declare(strict_types=1);

namespace Lkrms\Support\Catalog;

use Lkrms\Concept\Dictionary;

/**
 * Useful PCRE regular expressions
 *
 */
final class RegularExpression extends Dictionary
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
     * @see RegularExpression::PHP_IDENTIFIER
     */
    public const PHP_TYPE = '(?:\\\\?' . self::PHP_IDENTIFIER . ')+';

    /**
     * A valid PHP DocBlock, i.e. a DocComment containing a single PHPDoc
     * structure
     *
     * @link https://github.com/php-fig/fig-standards/blob/master/proposed/phpdoc.md#3-definitions
     */
    public const PHP_DOCBLOCK = <<<'REGEX'
        (?x) ^ /\*\*
        (?P<content>
          # Text immediately after the opening delimiter
          .*
          # Newlines and any subsequent text
          (?: \r ? \n \h * \* .* )*
          # Optional newline and whitespace before the closing delimiter
          (?: \r ? \n \h * )?
        )
        \*/ $
        REGEX;

    public static function delimit(
        string $regex,
        bool $anchor = false,
        bool $utf8 = true,
        string $delimiter = '/'
    ): string {
        $regex = str_replace($delimiter, '\\' . $delimiter, $regex);
        $modifier = $utf8 ? 'u' : '';

        return $anchor
            ? "$delimiter^$regex\$$delimiter$modifier"
            : "$delimiter$regex$delimiter$modifier";
    }
}
