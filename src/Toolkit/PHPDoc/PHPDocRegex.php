<?php declare(strict_types=1);

namespace Salient\PHPDoc;

/**
 * @internal
 */
interface PHPDocRegex
{
    /**
     * A valid PHP DocBlock, i.e. a DocComment containing a single PHPDoc
     * structure
     *
     * @link https://github.com/php-fig/fig-standards/blob/master/proposed/phpdoc.md#3-definitions
     */
    public const PHP_DOCBLOCK = <<<'REGEX'
(?x)
/\*\* (?= \s )
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
\h*+ @ (?<tag> [[:alpha:]\\] [[:alnum:]\\_-]*+ ) (?= [\s(:] | $ )
REGEX;

    /**
     * A value in a PHPDoc tag, e.g. the default value of a "@method" parameter
     */
    public const PHPDOC_VALUE = <<<'REGEX'
(?x)
(?(DEFINE)
  (?<no_comma>   [^"'()<>[\]{}\s,]++ )
  (?<no_bracket> [^"'()<>[\]{}]++ )
)
(?: \s*+ (?: (?&no_comma) | (
  \( (?: (?&no_bracket) | (?-1) )*+ \) |
   < (?: (?&no_bracket) | (?-1) )*+ >  |
  \[ (?: (?&no_bracket) | (?-1) )*+ \] |
  \{ (?: (?&no_bracket) | (?-1) )*+ \} |
   " (?: [^"\\] | \\ . )*+ " |
   ' (?: [^'\\] | \\ . )*+ '
) ) )+
REGEX;

    /**
     * A valid PHPDoc type
     *
     * Recursively matches plain, union and intersection types comprised of:
     *
     * - `*` (used in star projections like `Collection<*>`)
     * - `$this`
     * - string, float or integer literals
     * - callables with optional template, parameter and/or return types
     * - native or PHPDoc types with optional `::CONST_*`, `<Type,...>` and/or
     *   `{Foo?: string, ...<int>}`
     * - conditional return types
     * - enclosing parentheses
     *
     * In some locales, `\s` matches non-breaking space (`\xA0`), so `(?&sp)` is
     * used in contexts where the start of a PHP identifier would otherwise be
     * treated as whitespace.
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
  (?<variance_type> covariant | contravariant )
  (?<variance> (?&sp)*+ (?: (?&variance_type) (?&sp)*+ )? (?! (?&variance_type) \b ) )
  (?<param> \s*+ (?: & \s*+ )? (?: \.\.\. \s*+ )? (?: (?&variable) \s*+ )? =? )
  (?<trailing> (?: \s*+ , (?: \s*+ \.\.\. (?: \s*+ , )? )? )? )
)
(
  (?:
    \* |
    \$this |
    " (?: [^"\\] | \\ . )*+ " |
    ' (?: [^'\\] | \\ . )*+ ' |

    [+-]? (?:
      (?&exponent_dnum) |
      (?&dnum) |
      0x    [0-9a-f]++ (?: _ [0-9a-f]++ )*+ |
      0b    [01]++     (?: _ [01]++     )*+ |
      0o?   [0-7]++    (?: _ [0-7]++    )*+ |
      [1-9] [0-9]*+    (?: _ [0-9]++    )*+ |
      0
    ) |

    (?: (?: pure- (?! \\ ) )? (?: callable | \\? Closure ) ) \s*+
    (?: \s* < (?&variance) (?: (?&php_identifier) \s++ (?: of | as ) \b (?&sp)*+ )? (?-1)
        (?: \s*+ , (?&variance) (?: (?&php_identifier) \s++ (?: of | as ) \b (?&sp)*+ )? (?-1) )*+
        (?&trailing) \s*+ > )?
    \( (?&sp)*+ (?: (?-1) (?&param)
        (?: \s*+ , (?&sp)*+ (?-1) (?&param) )*+
        (?&trailing) \s*+ | \.\.\. \s*+ )? \)
    (?: \s* : (?&sp)*+ (?-1) )? |

    (?: \? (?&sp)*+ )?
    (?: (?&php_type) | (?&phpdoc_type) | (?&php_identifier) )
    (?: :: [[:alpha:]_\x80-\xff*] [[:alnum:]_\x80-\xff*]*+ )?
    (?: \s* < (?&variance) (?-1)
        (?: \s*+ , (?&variance) (?-1) )*+
        (?&trailing) \s*+ > )?
    (?: \s* \{ (?&sp)*+ (?: (?:
          (?: (?-1) \s*+ (?: \? \s*+ )? : (?&sp)*+ )? (?-1)
          (?: \s*+ , (?&sp)*+ (?: (?-1) \s*+ (?: \? \s*+ )? : (?&sp)*+ )? (?-1) )*+
          (?&trailing) | \.\.\. )?
        (?: (?<= \.\.\. ) \s*+ < (?&sp)*+ (?-1) (?: \s*+ , (?&sp)*+ (?-1) )? \s*+ > (?: \s*+ , )? )? \s*+ ) \} )*+ |

    (?: (?&variable) | (?&php_identifier) ) \s+ is (?: \s++ not )? \b (?&sp)*+ (?-1)
        \s*+ \? (?&sp)*+ (?-1)
        \s*+ :  (?&sp)*+ (?-1) |

    (?: \? \s*+ )? \( (?&sp)*+ (?-1) \s*+ \)
  )
  (?: \s* \[ (?&sp)*+ (?: (?-1) \s*+ )? \] )*+
  (?: \s* (?: \| | & ) (?&sp)* (?-1) )?
)
REGEX;
}
