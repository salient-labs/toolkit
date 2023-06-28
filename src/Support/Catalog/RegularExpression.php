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
    public const PHPDOC_TYPE = <<<'REGEX'
        (?xi)
        (?(DEFINE)
          # \s matches non-breaking space (\xA0) in some locales, so (?&sp) is used instead
          (?<sp> [\f\n\r\t\x0b ] )
          (?<lnum> [0-9]+ (?: _ [0-9]+ )* )
          (?<dnum> (?: [0-9]* (?: _ [0-9]+ )* \. (?&lnum) ) | (?: (?&lnum) \. [0-9]* (?: _ [0-9]+ )* ) )
          (?<exponent_dnum> (?: (?&lnum) | (?&dnum) ) e [+-]? (?&lnum) )
          (?<php_identifier> [[:alpha:]_\x80-\xff] [[:alnum:]_\x80-\xff]* )
          (?<php_type> \\ (?&php_identifier) (?: \\ (?&php_identifier) )* | (?&php_identifier) (?: \\ (?&php_identifier) )+ )
          (?<phpdoc_type> (?&php_identifier) (?: - [[:alnum:]_\x80-\xff]+ )+ )
          (?<variable> \$ (?&php_identifier) )
          (?<variance> covariant | contravariant )
          (?<param> \s*+ (?: & \s*+ )? (?: \.\.\. \s*+ )? (?: (?&variable) \s*+ )? =? )
          (?<trailing> (?: \s*+ , (?: \s*+ \.\.\. (?: \s*+ , )? )? )? \s*+ )
        )
        (
          (?:
            \* |

            \$this |

            # String
            ' (?: [^'\\]*+ | \\' | \\ )*+ ' |
            " (?: [^"\\]*+ | \\" | \\ )*+ " |

            # Number
            [+-]? (?:
              (?&exponent_dnum) |
              (?&dnum) |
              0x    [0-9a-f]++ (?: _ [0-9a-f]++ )*+ |
              0b    [01]++     (?: _ [01]++     )*+ |
              0o?   [0-7]++    (?: _ [0-7]++    )*+ |
              [1-9] [0-9]*+    (?: _ [0-9]++    )*+ |
              0
            ) |

            # Closure with optional parameter and return types
            (?: callable | Closure ) \s*+ \(
                (?&sp)*+ (?: (?-1) (?&param)
                    (?: \s*+ , (?&sp)*+ (?-1) (?&param) )*+
                    (?&trailing) | \.\.\. \s*+ )?
            \)
            (?: \s* : (?&sp)*+ (?-1) )? |

            # Native or PHPDoc type, possibly nullable, with optional
            # "::CONST_*", "<Type,...>", and/or "{0?:Type,...}"
            (?: \? (?&sp)*+ )?
            (?: (?&php_type) | (?&phpdoc_type) | (?&php_identifier) )
            (?: :: [[:alpha:]_\x80-\xff*] [[:alnum:]_\x80-\xff*]*+ )?
            (?: \s* < (?&sp)*+ (?: (?&variance) (?&sp)*+ )? (?! (?&variance) \b ) (?-1)
                (?: \s*+ , (?&sp)*+ (?: (?&variance) (?&sp)*+ )? (?! (?&variance) \b ) (?-1) )*+
                (?&trailing) > )?
            (?: \s* \{ (?&sp)*+ (?:
                (?: (?-1) \s*+ (?: \? \s*+ )? : (?&sp)*+ )? (?-1)
                (?: \s*+ , (?&sp)*+ (?: (?-1) \s*+ (?: \? \s*+ )? : (?&sp)*+ )? (?-1) )*+
                (?&trailing) | \.\.\. \s*+ )? \} )*+ |

            # Conditional return type
            (?: (?&variable) | (?&php_identifier) ) \s+ is (?: \s++ not )? \b (?&sp)*+ (?-1)
                \s*+ \? (?&sp)*+ (?-1)
                \s*+ :  (?&sp)*+ (?-1) |

            # Enclosing parentheses
            (?: \? \s*+ )? \( (?&sp)*+ (?-1) \s*+ \)
          )
          (?: \s* \[ (?&sp)*+ (?: (?-1) \s*+ )? \] )*+
          (?: \s* (?: \| | & ) (?&sp)* (?-1) )?
        )
        REGEX;

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
