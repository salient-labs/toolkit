<?php declare(strict_types=1);

namespace Salient\Core\Catalog;

use Salient\Core\AbstractDictionary;

/**
 * Useful regular expressions
 *
 * @extends AbstractDictionary<string>
 */
final class Regex extends AbstractDictionary
{
    /**
     * Zero or more backslash pairs with no preceding backslash
     */
    public const BEFORE_UNESCAPED = <<<'REGEX'
        (?<!\\)(?:\\\\)*
        REGEX;

    /**
     * A boolean string, e.g. "yes", "Y", "On", "TRUE", "enabled"
     */
    public const BOOLEAN_STRING = <<<'REGEX'
        (?xi)
        \s*+ (?:
          (?<true>  1 | on  | y(?:es)? | true  | enabled?  ) |
          (?<false> 0 | off | no?      | false | disabled? )
        ) \s*+
        REGEX;

    /**
     * An integer string
     */
    public const INTEGER_STRING = '\s*+[+-]?[0-9]+\s*+';

    /**
     * An [RFC4122]-compliant version 4 UUID
     */
    public const UUID = '(?i)[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}';

    /**
     * A 12-byte MongoDB ObjectId
     */
    public const MONGODB_OBJECTID = '(?i)[0-9a-f]{24}';

    /**
     * A valid PHP identifier, e.g. for variable names and classes
     *
     * @link https://www.php.net/manual/en/language.variables.basics.php
     * @link https://www.php.net/manual/en/language.oop5.basic.php
     */
    public const PHP_IDENTIFIER = '[[:alpha:]_\x80-\xff][[:alnum:]_\x80-\xff]*';

    /**
     * A valid PHP type, i.e. a PHP_IDENTIFIER with an optional namespace
     */
    public const PHP_TYPE = '(?:\\\\?' . self::PHP_IDENTIFIER . ')+';

    /**
     * A PHP union type, e.g. "A|B|C"
     */
    public const PHP_UNION_TYPE = self::PHP_TYPE . '(?:\|' . self::PHP_TYPE . ')+';

    /**
     * A PHP intersection type, e.g. "A&B&C"
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
     * PHP 8.0 union types are also matched. Reject {@see Regex::PHP_UNION_TYPE}
     * matches if this is not desirable.
     *
     * @link https://wiki.php.net/rfc/dnf_types
     */
    public const PHP_DNF_TYPE = self::PHP_DNF_SEGMENT . '(?:\|' . self::PHP_DNF_SEGMENT . ')+';

    /**
     * A valid PHP type, including union, intersection, and DNF types
     */
    public const PHP_FULL_TYPE = self::PHP_DNF_SEGMENT . '(?:\|' . self::PHP_DNF_SEGMENT . ')*';

    /**
     * A valid PHP DocBlock, i.e. a DocComment containing a single PHPDoc
     * structure
     *
     * @link https://github.com/php-fig/fig-standards/blob/master/proposed/phpdoc.md#3-definitions
     */
    public const PHP_DOCBLOCK = <<<'REGEX'
        (?x)
        /\*\*
        (?<content> (?:
          (?: (?! \*/ ) . )*+
          (?: (?<line> (?: \r\n | \n | \r ) \h* ) (?! \*/ ) \* )?
        )*+ (?&line)? )
        \*/
        REGEX;

    /**
     * A valid PHPDoc tag
     *
     * @link https://github.com/php-fig/fig-standards/blob/master/proposed/phpdoc.md#53-tags
     */
    public const PHPDOC_TAG = <<<'REGEX'
        (?x)
        (?<= ^ | \r\n | \n | \r )
        @ (?<tag> [[:alpha:]\\] [[:alnum:]\\_-]*+ ) (?= [\s(:] | $ )
        REGEX;

    /**
     * A valid PHPDoc type
     *
     * In some locales, `\s` matches non-breaking space (`\xA0`), so an
     * alternative (`(?&sp)`) is used in contexts where the start of a PHP
     * identifier would otherwise be parsed as whitespace.
     */
    public const PHPDOC_TYPE = <<<'REGEX'
        (?xi)
        (?(DEFINE)
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
        string $delimiter = '/'
    ): string {
        return $delimiter
            . str_replace($delimiter, '\\' . $delimiter, $regex)
            . $delimiter;
    }

    public static function anchorAndDelimit(
        string $regex,
        string $delimiter = '/'
    ): string {
        return $delimiter
            . '^'
            . str_replace($delimiter, '\\' . $delimiter, $regex)
            . '$'
            . $delimiter;
    }
}
