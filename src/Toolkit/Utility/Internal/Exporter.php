<?php declare(strict_types=1);

namespace Salient\Utility\Internal;

use Salient\Utility\Arr;
use Salient\Utility\Get;
use Salient\Utility\Regex;
use Salient\Utility\Str;

/**
 * @internal
 */
final class Exporter
{
    private string $Delimiter;
    private string $Arrow;
    private ?string $EscapeCharacters;
    private string $Tab;
    /** @var array<non-empty-string,true> */
    private array $Classes;
    /** @var array<non-empty-string,string> */
    private array $Constants;
    private string $Eol;
    private bool $MultiLine;
    private ?string $EscapeRegex = null;
    /** @var string[] */
    private array $EscapeSearch = [];
    /** @var string[] */
    private array $EscapeReplace = [];
    private ?string $ConstantRegex = null;

    /**
     * @param non-empty-string[] $classes
     * @param array<non-empty-string,string> $constants
     */
    public function __construct(
        string $delimiter,
        string $arrow,
        ?string $escapeCharacters,
        string $tab,
        array $classes,
        array $constants
    ) {
        $this->Delimiter = $delimiter;
        $this->Arrow = $arrow;
        $this->EscapeCharacters = Str::coalesce($escapeCharacters, null);
        $this->Tab = $tab;
        $this->Classes = array_fill_keys($classes, true);
        $this->Constants = $constants;
        $this->Eol = (string) Get::eol($delimiter);
        $this->MultiLine = $this->Eol !== '';

        if ($this->EscapeCharacters !== null) {
            $this->EscapeRegex = Regex::quoteCharacters($this->EscapeCharacters, '/');
            foreach (str_split($this->EscapeCharacters) as $character) {
                $this->EscapeSearch[] = sprintf(
                    '/((?<!\\\\)(?:\\\\\\\\)*)%s/',
                    Regex::quote(addcslashes($character, $character), '/'),
                );
                $this->EscapeReplace[] = sprintf('$1\x%02x', ord($character));
            }
        }

        if ($this->Constants) {
            // Match longest values first
            uksort($this->Constants, fn($a, $b) => strlen($b) <=> strlen($a));
            foreach (array_keys($this->Constants) as $string) {
                $regex[] = Regex::quote($string, '/');
            }
            $this->ConstantRegex = count($regex) === 1
                ? '/' . $regex[0] . '/'
                : '/(?:' . implode('|', $regex) . ')/';
        }
    }

    /**
     * @param mixed $value
     */
    public function export($value): string
    {
        return $this->doExport($value);
    }

    /**
     * @param mixed $value
     */
    private function doExport(
        $value,
        string $indent = '',
        bool $constants = true
    ): string {
        if ($value === null) {
            return 'null';
        }

        if (is_string($value)) {
            if ($this->Classes[$value] ?? null) {
                return $value . '::class';
            }

            if ($constants && $this->ConstantRegex !== null) {
                $parts = [];
                while (Regex::match($this->ConstantRegex, $value, $matches, \PREG_OFFSET_CAPTURE)) {
                    if ($matches[0][1] > 0) {
                        $parts[] = substr($value, 0, $matches[0][1]);
                    }
                    $parts[] = $matches[0][0];
                    $value = substr($value, $matches[0][1] + strlen($matches[0][0]));
                }
                if ($parts) {
                    if ($value !== '') {
                        $parts[] = $value;
                    }
                    foreach ($parts as $part) {
                        $code[] = $this->Constants[$part]
                            ?? $this->doExport($part, $indent, false);
                    }
                    return implode(' . ', $code);
                }
            }

            if ($this->MultiLine) {
                $escape = '';
                $match = '';
            } else {
                $escape = "\n\r";
                $match = '\n\r';
            }

            // If `$value` contains valid UTF-8 sequences, don't escape leading
            // bytes (\xc2 -> \xf4) or continuation bytes (\x80 -> \xbf)
            $utf8 = false;
            if (mb_check_encoding($value, 'UTF-8')) {
                $escape .= "\x7f\xc0\xc1\xf5..\xff";
                $match .= '\x7f\xc0\xc1\xf5-\xff';
                $utf8 = true;
            } else {
                $escape .= "\x7f..\xff";
                $match .= '\x7f-\xff';
            }

            $escape .= $this->EscapeCharacters;
            $match .= $this->EscapeRegex;

            // \0..\t\v\f\x0e..\x1f is equivalent to \0..\x1f without \n and \r
            $double = addcslashes($value, "\0..\t\v\f\x0e..\x1f\"\$\\{$escape}");

            // Convert ignorable code points to "\u{xxxx}" unless they belong to
            // an extended grapheme cluster (e.g. an emoji sequence)
            $utf8Escapes = 0;
            if ($utf8) {
                $double = Regex::replaceCallback(
                    '/(?![\x00-\x7f])\X/u',
                    function ($matches) use (&$utf8Escapes) {
                        if (!Regex::match('/^' . Regex::INVISIBLE_CHAR . '$/u', $matches[0])) {
                            return $matches[0];
                        }
                        $utf8Escapes++;
                        return sprintf('\u{%04X}', mb_ord($matches[0]));
                    },
                    $double,
                );
            }

            if (
                $utf8Escapes
                || Regex::match("/[\\x00-\\x09\\x0b\\x0c\\x0e-\\x1f{$match}]/", $value)
            ) {
                // Convert octal notation to hex (e.g. "\177" to "\x7f") and
                // correct for differences between C and PHP escape sequences:
                // - recognised by PHP: \0 \e \f \n \r \t \v
                // - applied by addcslashes: \000 \033 \a \b \f \n \r \t \v
                $double = Regex::replaceCallback(
                    '/((?<!\\\\)(?:\\\\\\\\)*)\\\\(?:(?<NUL>000(?![0-7]))|(?<octal>[0-7]{3})|(?<cslash>[ab]))/',
                    fn(array $matches): string =>
                        $matches[1]
                        . ($matches['NUL'] !== null
                            ? '\0'
                            : ($matches['octal'] !== null
                                ? (($dec = octdec($matches['octal'])) === 27
                                    ? '\e'
                                    : sprintf('\x%02x', $dec))
                                : sprintf('\x%02x', ['a' => 7, 'b' => 8][$matches['cslash']]))),
                    $double,
                    -1,
                    $count,
                    \PREG_UNMATCHED_AS_NULL,
                );

                // Replace characters in `$this->Escape` with the equivalent
                // hexadecimal escape
                if ($this->EscapeSearch) {
                    $double = Regex::replace($this->EscapeSearch, $this->EscapeReplace, $double);
                }

                // Remove unnecessary backslashes
                $double = Regex::replace(
                    '/(?<!\\\\)\\\\\\\\(?![nrtvef\\\\$"]|[0-7]|x[0-9a-fA-F]|u\{[0-9a-fA-F]+\}|$)/D',
                    '\\',
                    $double
                );

                return '"' . $double . '"';
            }
        }

        if (!is_array($value)) {
            $result = var_export($value, true);
            if (is_float($value)) {
                return Str::lower($result);
            }
            return $result;
        }

        if (!$value) {
            return '[]';
        }

        $prefix = '[';
        $suffix = ']';
        $glue = $this->Delimiter;

        if ($this->MultiLine) {
            // Add trailing commas
            $suffix = $this->Delimiter . $indent . $suffix;
            $indent .= $this->Tab;
            $prefix .= $this->Eol . $indent;
            $glue .= $indent;
        }

        $isList = Arr::isList($value);
        if (!$isList) {
            $isMixedList = false;
            $keys = 0;
            foreach (array_keys($value) as $key) {
                if (is_int($key)) {
                    if ($keys++ !== $key) {
                        $isMixedList = false;
                        break;
                    }
                    $isMixedList = true;
                }
            }
        }

        foreach ($value as $key => $value) {
            $value = $this->doExport($value, $indent);
            if ($isList || ($isMixedList && is_int($key))) {
                $values[] = $value;
            } else {
                $key = $this->doExport($key, $indent);
                $values[] = $key . $this->Arrow . $value;
            }
        }

        return $prefix . implode($glue, $values) . $suffix;
    }
}
