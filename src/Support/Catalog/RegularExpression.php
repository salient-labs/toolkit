<?php declare(strict_types=1);

namespace Lkrms\Support\Catalog;

use Lkrms\Concept\Dictionary;

/**
 * Useful PCRE regular expressions
 *
 * @extends Dictionary<string>
 */
final class RegularExpression extends Dictionary
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
     * An [RFC3986]-compliant URI scheme
     */
    public const URI_SCHEME = '(?i)[a-z][-a-z0-9+.]*';

    /**
     * An [RFC3986]-compliant URI host
     */
    public const URI_HOST = '(?i)(([-a-z0-9!$&\'()*+,.;=_~]|%[0-9a-f]{2})++|\[[0-9a-f:]++\])';

    /**
     * An [RFC3986]-compliant URI
     */
    public const URI = <<<'REGEX'
        (?xi)
        (?(DEFINE)
          # Percent-encoded octets in this set should be decoded by normalisers
          (?<unreserved> [-a-z0-9._~] )
          (?<sub_delims> [!$&'()*+,;=] )
          # Case-insensitive but normalisers should use uppercase
          (?<pct_encoded> % [0-9a-f]{2} )
          (?<reg_char> (?&unreserved) | (?&pct_encoded) | (?&sub_delims) )
          (?<pchar> (?&reg_char) | [:@] )
        )
        # Case-insensitive but canonical form is lowercase
        (?: (?<scheme> [a-z] [-a-z0-9+.]* ) : )?+
        (?:
          //
          (?<authority>
            (?:
              (?<userinfo>
                (?<user> (?&reg_char)* )
                (?: : (?<pass> (?: (?&reg_char) | : )* ) )?
              )
              @
            )?+
            # Case-insensitive
            (?<host> (?&reg_char)++ | \[ (?<ipv6address> [0-9a-f:]++ ) \] )
            (?: : (?<port> [0-9]+ ) )?+
          )
          # Path after authority must be empty or begin with "/"
          (?= / | \? | \# | $ ) |
          # Path cannot begin with "//" except after authority
          (?= / ) (?! // ) |
          # Rootless paths can only begin with a ":" segment after scheme
          (?(<scheme>) (?= (?&pchar) ) | (?= (?&reg_char) | @ ) (?! [^/:]++ : ) ) |
          (?= \? | \# | $ )
        )
        (?<path> (?: (?&pchar) | / )*+ )
        (?: \? (?<query>    (?: (?&pchar) | [?/] )* ) )?+
        (?: \# (?<fragment> (?: (?&pchar) | [?/] )* ) )?+
        REGEX;

    /**
     * An [RFC7230]-compliant HTTP header field name
     */
    public const HTTP_HEADER_FIELD_NAME = '(?i)[-0-9a-z!#$%&\'*+.^_`|~]++';

    /**
     * An [RFC7230]-compliant HTTP header field value
     */
    public const HTTP_HEADER_FIELD_VALUE = '([\x21-\x7e\x80-\xff]++(?:\h++[\x21-\x7e\x80-\xff]++)*+)?';

    /**
     * An [RFC7230]-compliant HTTP header field or continuation thereof
     */
    public const HTTP_HEADER_FIELD = <<<'REGEX'
        (?xi)
        (?(DEFINE)
          (?<token> [-0-9a-z!#$%&'*+.^_`|~]++ )
          (?<field_vchar> [\x21-\x7e\x80-\xff]++ )
          (?<field_content> (?&field_vchar) (?: \h++ (?&field_vchar) )*+ )
        )
        (?:
          (?<name> (?&token) ) (?<bad_whitespace> \h++ )?+ : \h*+ (?<value> (?&field_content)? ) |
          \h++ (?<extended> (?&field_content)? )
        )
        (?<carry> \h++ )?
        REGEX;

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
     * PHP 8.0 union types are also matched. Reject
     * {@see RegularExpression::PHP_UNION_TYPE} matches if this is not
     * desirable.
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
