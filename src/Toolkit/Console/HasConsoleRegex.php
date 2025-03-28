<?php declare(strict_types=1);

namespace Salient\Console;

/**
 * @internal
 */
interface HasConsoleRegex
{
    /**
     * Splits the subject into text, code blocks and code spans
     */
    public const FORMAT_REGEX = <<<'REGEX'
/
(?(DEFINE)
    (?<endofline> \h*+ \n )
    (?<endofblock> ^ \k<indent> \k<fence> \h*+ $ )
    (?<endofspan> \k<backtickstring> (?! ` ) )
)
# No gaps between matches
\G
# No empty matches
(?= . )
# Match indentation early so horizontal whitespace before code blocks is not
# mistaken for text
(?<indent> ^ \h*+ )?
(?:
    # Whitespace before paragraphs
    (?<breaks> (?&endofline)+ ) |
    # Everything except unescaped backticks until the next paragraph
    (?<text> (?> (?: [^\\`\n]+ | \\ [-\\!"\#$%&'()*+,.\/:;<=>?@[\]^_`{|}~\n] | \\ | \n (?! (?&endofline) ) )+ (?&endofline)* ) ) |
    # CommonMark-compliant fenced code blocks
    (?> (?(indent)
        (?> (?<fence> ```+ ) (?<infostring> [^\n]* ) \n )
        # Match empty blocks--with no trailing newline--and blocks with an empty
        # line by making the subsequent newline conditional on inblock
        (?<block> (?> (?<inblock> (?: (?! (?&endofblock) ) (?: \k<indent> | (?= (?&endofline) ) ) [^\n]* (?: (?= \n (?&endofblock) ) | \n | \z ) )+ )? ) )
        # Allow code fences to terminate at the end of the subject
        (?: (?(inblock) \n ) (?&endofblock) | \z ) | \z
    ) ) |
    # CommonMark-compliant code spans
    (?<backtickstring> (?> `+ ) ) (?<span> (?> (?: [^`]+ | (?! (?&endofspan) ) `+ )* ) ) (?&endofspan) |
    # Unmatched backticks
    (?<extra> `+ ) |
    \z
) /mxs
REGEX;

    /**
     * Matches inline formatting tags used outside code blocks and spans
     */
    public const TAG_REGEX = <<<'REGEX'
/
(?(DEFINE)
    (?<esc> \\ [-\\!"\#$%&'()*+,.\/:;<=>?@[\]^_`{|}~] | \\ )
)
(?<! \\ ) (?: \\\\ )* \K (?|
    \b  (?<tag> _ {1,3}+ )  (?! \s ) (?> (?<text> (?: [^_\\]+ |    (?&esc) | (?! (?<! \s ) \k<tag> \b ) _ + )* ) ) (?<! \s ) \k<tag> \b |
        (?<tag> \* {1,3}+ ) (?! \s ) (?> (?<text> (?: [^*\\]+ |    (?&esc) | (?! (?<! \s ) \k<tag> ) \* + )* ) )   (?<! \s ) \k<tag>    |
        (?<tag> < )         (?! \s ) (?> (?<text> (?: [^>\\]+ |    (?&esc) | (?! (?<! \s ) > ) > + )* ) )          (?<! \s ) >          |
        (?<tag> ~~ )        (?! \s ) (?> (?<text> (?: [^~\\]+ |    (?&esc) | (?! (?<! \s ) ~~ ) ~ + )* ) )         (?<! \s ) ~~         |
    ^   (?<tag> \#\# ) \h+           (?> (?<text> (?: [^\#\s\\]+ | (?&esc) | \#+ (?! \h* $ ) | \h++ (?! (?: \#+ \h* )? $ ) )* ) ) (?: \h+ \#+ | \h* ) $
) /mx
REGEX;

    /**
     * Matches a CommonMark-compliant backslash escape, or an escaped line break
     * with an optional leading space
     */
    public const ESCAPE_REGEX = <<<'REGEX'
/
(?|
    \\ ( [-\\ !"\#$%&'()*+,.\/:;<=>?@[\]^_`{|}~] ) |
    # Lookbehind assertions are unnecessary because the first branch matches
    # escaped spaces and backslashes
    \  ? \\ ( \n )
) /x
REGEX;
}
