<?php declare(strict_types=1);

namespace Salient\Contract\Core;

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
     * Characters with Unicode's "blank" or "Default_Ignorable_Code_Point"
     * properties
     *
     * @link https://util.unicode.org/UnicodeJsps/list-unicodeset.jsp?a=[:blank=Yes:]
     * @link https://util.unicode.org/UnicodeJsps/list-unicodeset.jsp?a=[:Default_Ignorable_Code_Point=Yes:]
     */
    public const INVISIBLE_CHAR = '[\x{00A0}\x{2000}-\x{200A}\x{202F}\x{205F}\x{3000}\x{00AD}\x{17B4}\x{17B5}\x{034F}\x{061C}\x{115F}\x{180B}-\x{180F}\x{200B}-\x{200F}\x{202A}-\x{202E}\x{1160}\x{2060}-\x{2064}\x{2065}\x{2066}-\x{206F}\x{3164}\x{FE00}-\x{FEFF}\x{FFA0}\x{FFF0}-\x{FFF8}\x{1BCA0}-\x{1BCA3}\x{1D173}-\x{1D17A}\x{E0000}\x{E0001}\x{E01F0}-\x{E0FFF}\x{E0002}-\x{E001F}\x{E0020}-\x{E007F}\x{E0080}-\x{E00FF}\x{E0100}-\x{E01EF}]';

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
     * A token in an [RFC7230]-compliant HTTP message
     */
    public const HTTP_TOKEN = '(?i)[-0-9a-z!#$%&\'*+.^_`|~]++';

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

    # Closure with optional "<Type,...>", and optional parameter and
    # return types
    (?: callable | \\? Closure ) \s*+
    (?: \s* < (?&sp)*+ (?: (?&variance) (?&sp)*+ )? (?! (?&variance) \b ) (?: (?&php_identifier) \s++ (?: of | as ) \b (?&sp)*+ )? (?-1)
        (?: \s*+ , (?&sp)*+ (?: (?&variance) (?&sp)*+ )? (?! (?&variance) \b ) (?: (?&php_identifier) \s++ (?: of | as ) \b (?&sp)*+ )? (?-1) )*+
        (?&trailing) > )?
    \( (?&sp)*+ (?: (?-1) (?&param)
        (?: \s*+ , (?&sp)*+ (?-1) (?&param) )*+
        (?&trailing) | \.\.\. \s*+ )? \)
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
}
