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
     * A PHP union type, e.g. "A|B|C"
     *
     */
    public const PHP_UNION_TYPE = self::PHP_TYPE . '(?:\|' . self::PHP_TYPE . ')+';

    /**
     * A PHP intersection type, e.g. "A&B&C"
     *
     */
    public const PHP_INTERSECTION_TYPE = self::PHP_TYPE . '(?:&' . self::PHP_TYPE . ')+';

    /**
     * One of the segments in a PHP DNF type, e.g. "A" or "(B&C)"
     *
     * @link https://wiki.php.net/rfc/dnf_types
     */
    public const PHP_DNF_SEGMENT = '(?:' . self::PHP_TYPE . '|\(' . self::PHP_INTERSECTION_TYPE . '\))';

    /**
     * A PHP DNF type, e.g. "A|(B&C)|D|E"
     *
     * PHP 8.0 union types are also matched. Reject
     * {@see RegularExpression::PHP_UNION_TYPE} matches if this is not
     * desirable.
     *
     * @link https://wiki.php.net/rfc/dnf_types
     */
    public const PHP_DNF_TYPE = self::PHP_DNF_SEGMENT . '(?:\|' . self::PHP_DNF_SEGMENT . ')+';

    /**
     * A valid PHP type, including union, intersection, and DNF types
     *
     */
    public const PHP_FULL_TYPE = self::PHP_DNF_SEGMENT . '(?:\|' . self::PHP_DNF_SEGMENT . ')*';

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
          (?: ( \r \n | \n | \r ) \h * \* .* )*
          # Optional newline and whitespace before the closing delimiter
          (?: ( \r \n | \n | \r ) \h * )?
        )
        \*/ $
        REGEX;

    /**
     * A valid PHPDoc tag
     *
     * Inline tags are matched. Metadata and descriptions are not.
     *
     * @link https://github.com/php-fig/fig-standards/blob/master/proposed/phpdoc.md#53-tags
     */
    public const PHPDOC_TAG = '(^|(?<=\r\n|\n|\r))@(?P<tag>[[:alpha:]\\\\][[:alnum:]\\\\_-]*)(?=\s|[(:]|$)';

    /**
     * A valid PHPDoc type
     *
     */
    public const PHPDOC_TYPE = '(' . self::PHP_FULL_TYPE . '|[[:alpha:]_\x80-\xff](?:(?:-|::)?[[:alnum:]_\x80-\xff]+)*(?:\*)?(?:\[\])?(?:<(?-1)(?:,(?-1))*>)?)';

    public static function delimit(
        string $regex,
        string $delimiter = '/',
        bool $utf8 = true
    ): string {
        return $delimiter
            . str_replace($delimiter, '\\' . $delimiter, $regex)
            . $delimiter
            . ($utf8 ? 'u' : '');
    }

    public static function anchorAndDelimit(
        string $regex,
        string $delimiter = '/',
        bool $utf8 = true
    ): string {
        return $delimiter
            . '^'
            . str_replace($delimiter, '\\' . $delimiter, $regex)
            . '$'
            . $delimiter
            . ($utf8 ? 'u' : '');
    }
}
