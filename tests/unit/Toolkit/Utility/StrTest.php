<?php declare(strict_types=1);

namespace Salient\Tests\Utility;

use Salient\Tests\TestCase;
use Salient\Utility\Get;
use Salient\Utility\Str;
use Closure;
use InvalidArgumentException;
use ReflectionParameter;

/**
 * @covers \Salient\Utility\Str
 */
final class StrTest extends TestCase
{
    /**
     * @dataProvider coalesceProvider
     */
    public function testCoalesce(?string $expected, ?string ...$strings): void
    {
        $this->assertSame($expected, Str::coalesce(...$strings));
    }

    /**
     * @return array<array<string|null>>
     */
    public static function coalesceProvider(): array
    {
        return [
            [null, null],
            ['', ''],
            [null, '', null],
            ['', null, ''],
            [null, null, '', null],
            ['foo', '', 'foo'],
            ['foo', 'foo', ''],
            ['foo', null, 'foo'],
            ['foo', 'foo', null],
            ['foo', null, 'foo', null],
            ['a', '', null, 'a', null],
            ['0', '0', '1', null],
        ];
    }

    /**
     * @dataProvider setEolProvider
     */
    public function testSetEol(string $expected, string $string, string $eol = "\n"): void
    {
        $this->assertSame($expected, Str::setEol($string, $eol));
    }

    /**
     * @return array<array{string,string,2?:string}>
     */
    public static function setEolProvider(): array
    {
        return [
            [
                '',
                '',
            ],
            [
                "\n",
                "\n",
            ],
            [
                "\n",
                "\r\n",
            ],
            [
                "\r",
                "\r\n",
                "\r",
            ],
            [
                "\r\r",
                "\n\r",
                "\r",
            ],
            [
                "line1\nline2\n",
                "line1\nline2\n",
            ],
            [
                "line1\nline2\n",
                "line1\r\nline2\r\n",
            ],
            [
                "line1\nline2\n",
                "line1\rline2\r",
            ],
            [
                "line1\nline2\nline3\nline4\n\n",
                "line1\r\nline2\nline3\rline4\n\r",
            ],
            [
                "line1\rline2\rline3\rline4\r\r",
                "line1\r\nline2\nline3\rline4\n\r",
                "\r",
            ],
            [
                "line1\r\nline2\r\nline3\r\nline4\r\n\r\n",
                "line1\r\nline2\nline3\rline4\n\r",
                "\r\n",
            ],
            [
                "line1<br />\nline2<br />\nline3<br />\nline4<br />\n<br />\n",
                "line1\r\nline2\nline3\rline4\n\r",
                "<br />\n",
            ],
        ];
    }

    /**
     * @dataProvider trimNativeEolProvider
     */
    public function testTrimNativeEol(string $expected, string $value): void
    {
        $this->assertSame($expected, Str::trimNativeEol($value));
    }

    /**
     * @return array<string,array{string,string}>
     */
    public static function trimNativeEolProvider(): array
    {
        $eol2 = \PHP_EOL === "\n"
            ? "\r\n"
            : "\n";

        return [
            'empty' => ['', ''],
            'no EOL' => ['foo', 'foo'],
            'native EOL' => ['foo', 'foo' . \PHP_EOL],
            'native EOL x2' => ['foo', 'foo' . \PHP_EOL . \PHP_EOL],
            'native EOL x3' => ['foo', 'foo' . \PHP_EOL . \PHP_EOL . \PHP_EOL],
            'leading native EOL' => [\PHP_EOL . 'foo', \PHP_EOL . 'foo'],
            'inner native EOL' => ['foo' . \PHP_EOL . 'bar', 'foo' . \PHP_EOL . 'bar'],
            'mixed native EOL' => [\PHP_EOL . 'foo' . \PHP_EOL . 'bar', \PHP_EOL . 'foo' . \PHP_EOL . 'bar' . \PHP_EOL . \PHP_EOL],
            'non-native EOL' => ['foo' . $eol2, 'foo' . $eol2],
            'non-native EOL x2' => ['foo' . $eol2 . $eol2, 'foo' . $eol2 . $eol2],
            'non-native EOL x3' => ['foo' . $eol2 . $eol2 . $eol2, 'foo' . $eol2 . $eol2 . $eol2],
            'non-native EOL + native EOL' => ['foo' . $eol2, 'foo' . $eol2 . \PHP_EOL],
            'non-native EOL x2 + native EOL x2' => ['foo' . $eol2 . $eol2, 'foo' . $eol2 . $eol2 . \PHP_EOL . \PHP_EOL],
            'non-native EOL x3 + native EOL x3' => ['foo' . $eol2 . $eol2 . $eol2, 'foo' . $eol2 . $eol2 . $eol2 . \PHP_EOL . \PHP_EOL . \PHP_EOL],
            '(non-native EOL + native EOL) x3' => ['foo' . $eol2 . \PHP_EOL . $eol2 . \PHP_EOL . $eol2, 'foo' . $eol2 . \PHP_EOL . $eol2 . \PHP_EOL . $eol2 . \PHP_EOL],
            'native EOL + non-native EOL' => ['foo' . \PHP_EOL . $eol2, 'foo' . \PHP_EOL . $eol2],
            'native EOL x2 + non-native EOL x2' => ['foo' . \PHP_EOL . \PHP_EOL . $eol2 . $eol2, 'foo' . \PHP_EOL . \PHP_EOL . $eol2 . $eol2],
            'native EOL x3 + non-native EOL x3' => ['foo' . \PHP_EOL . \PHP_EOL . \PHP_EOL . $eol2 . $eol2 . $eol2, 'foo' . \PHP_EOL . \PHP_EOL . \PHP_EOL . $eol2 . $eol2 . $eol2],
            '(native EOL + non-native EOL) x3' => ['foo' . \PHP_EOL . $eol2 . \PHP_EOL . $eol2 . \PHP_EOL . $eol2, 'foo' . \PHP_EOL . $eol2 . \PHP_EOL . $eol2 . \PHP_EOL . $eol2],
        ];
    }

    /**
     * @dataProvider eolToNativeProvider
     */
    public function testEolToNative(string $expected, string $string): void
    {
        $this->assertSame($expected, Str::eolToNative($string));
    }

    /**
     * @return array<array{string,string}>
     */
    public static function eolToNativeProvider(): array
    {
        return [
            [
                '',
                '',
            ],
            [
                <<<'EOF'


EOF,
                "\n",
            ],
            [
                <<<'EOF'
line1
line2


EOF,
                "line1\nline2\n\n",
            ],
        ];
    }

    /**
     * @dataProvider eolFromNativeProvider
     */
    public function testEolFromNative(string $expected, string $string): void
    {
        $this->assertSame($expected, Str::eolFromNative($string));
    }

    /**
     * @return array<array{string,string}>
     */
    public static function eolFromNativeProvider(): array
    {
        return [
            [
                '',
                '',
            ],
            [
                "\n",
                <<<'EOF'


EOF,
            ],
            [
                "line1\nline2\n\n",
                <<<'EOF'
line1
line2


EOF,
            ],
        ];
    }

    /**
     * @dataProvider caseConversionProvider
     */
    public function testCaseConversion(
        string $expectedLower,
        string $expectedUpper,
        string $expectedUpperFirst,
        string $string
    ): void {
        $this->assertSame($expectedLower, Str::lower($string));
        $this->assertSame($expectedUpper, Str::upper($string));
        $this->assertSame($expectedUpperFirst, Str::upperFirst($string));
    }

    /**
     * @return array<array{string,string,string,string}>
     */
    public static function caseConversionProvider(): array
    {
        return [
            ['', '', '', ''],
            ['foobar', 'FOOBAR', 'FoObAr', 'foObAr'],
            ['foo bar', 'FOO BAR', 'FoO bAr', 'foO bAr'],
            ['123 foo', '123 FOO', '123 foo', '123 foo'],
            ['äëïöüÿ', 'äëïöüÿ', 'äëïöüÿ', 'äëïöüÿ'],
        ];
    }

    /**
     * @dataProvider matchCaseProvider
     */
    public function testMatchCase(string $expected, string $string, string $match): void
    {
        $this->assertSame($expected, Str::matchCase($string, $match));
    }

    /**
     * @return array<string,array{string,string,string}>
     */
    public static function matchCaseProvider(): array
    {
        return [
            'uppercase' => ['HELLO', 'hElLo', 'WORLD'],
            'lowercase' => ['hello', 'hElLo', 'world'],
            'title case' => ['Hello', 'hElLo', 'World'],
            'uppercase char' => ['Hello', 'hElLo', 'A'],
            'lowercase char' => ['hello', 'hElLo', 'a'],
            'uppercase + space' => ['HELLO', 'hElLo', ' WORLD '],
            'lowercase + space' => ['hello', 'hElLo', ' world '],
            'title case + space' => ['Hello', 'hElLo', ' World '],
            'uppercase char + space' => ['Hello', 'hElLo', ' A '],
            'lowercase char + space' => ['hello', 'hElLo', ' a '],
            'empty' => ['hElLo', 'hElLo', ''],
            'mixed case' => ['hElLo', 'hElLo', 'wORLD'],
            'numeric' => ['hElLo', 'hElLo', '12345'],
        ];
    }

    /**
     * @dataProvider startsWithProvider
     *
     * @param iterable<string>|string $needles
     */
    public function testStartsWith(bool $expected, string $haystack, $needles, bool $ignoreCase = false): void
    {
        $this->assertSame($expected, Str::startsWith($haystack, $needles, $ignoreCase));
    }

    /**
     * @return array<array{bool,string,iterable<string>|string,3?:bool}>
     */
    public static function startsWithProvider(): array
    {
        return [
            [true, 'hello world', 'hello'],
            [true, 'hello world', 'he'],
            [false, 'hello world', 'world'],
            [false, 'hello world', ''],
            [true, 'hello world', ['world', 'hello']],
            [false, 'hello world', ['world', 'planet']],
            [true, 'hello world', ['wo', 'he']],
            [false, '', 'hello'],
            [false, 'hello', 'world'],
            [false, 'hello world', 'HELLO'],
            [false, 'hello world', 'HE'],
            [true, 'hello world', 'HELLO', true],
            [true, 'hello world', 'HE', true],
            [false, 'hello world', 'WORLD', true],
            [true, 'hello world', ['WORLD', 'HELLO'], true],
        ];
    }

    /**
     * @dataProvider endsWithProvider
     *
     * @param iterable<string>|string $needles
     */
    public function testEndsWith(bool $expected, string $haystack, $needles, bool $ignoreCase = false): void
    {
        $this->assertSame($expected, Str::endsWith($haystack, $needles, $ignoreCase));
    }

    /**
     * @return array<array{bool,string,iterable<string>|string,3?:bool}>
     */
    public static function endsWithProvider(): array
    {
        return [
            [true, 'hello world', 'world'],
            [false, 'hello world', 'hello'],
            [true, 'hello world', ['planet', 'world']],
            [false, 'hello world', 'planet'],
            [false, 'hello', ''],
            [false, '', 'hello'],
            [true, 'hello', 'o'],
            [false, 'hello', 'a'],
            [false, 'hello world', 'WORLD'],
            [false, 'hello', 'O'],
            [true, 'hello world', 'WORLD', true],
            [false, 'hello world', 'HELLO', true],
            [true, 'hello world', ['PLANET', 'WORLD'], true],
            [true, 'hello', 'O', true],
        ];
    }

    /**
     * @dataProvider isAsciiProvider
     */
    public function testIsAscii(bool $expected, string $string): void
    {
        $this->assertSame($expected, Str::isAscii($string));
    }

    /**
     * @return array<array{bool,string}>
     */
    public static function isAsciiProvider(): array
    {
        return [
            [false, 'äëïöüÿ'],
            [false, '👩🏼‍🚒'],
            [true, ''],
            [true, 'Hello, world!'],
        ];
    }

    /**
     * @dataProvider normaliseProvider
     */
    public function testNormalise(
        string $expected,
        string $text
    ): void {
        $this->assertSame($expected, Str::normalise($text));
    }

    /**
     * @return array<string[]>
     */
    public static function normaliseProvider(): array
    {
        return [
            ['A AND B AND C', 'a & b & c'],
            ['A AND B AND C', '& a & b & c &'],
            ['HISTORY ANCIENT', 'History — Ancient'],
            ['HISTORY ANCIENT', 'History  —  Ancient'],
            ['IT', 'I.T.'],
            ['IT', 'IT. '],
            ['IT', 'it'],
            ['IT', '🚀IT🚀'],
            ['MATHEMATICS', ' & Mathematics'],
            ['MATHEMATICS', '_& Mathematics'],
            ['MATHEMATICS', '&mathematics'],
            ['SCIENCE AND TECHNOLOGY', 'Science & Technology'],
            ['SCIENCE AND TECHNOLOGY', 'Science_&_Technology'],
            ['SCIENCE AND TECHNOLOGY', 'science&technology'],
            ['SCIENCE TECHNOLOGY', 'Science && Technology'],
            ['SCIENCE TECHNOLOGY', 'Science_&&_Technology'],
            ['SCIENCE TECHNOLOGY', 'science&&technology'],
        ];
    }

    /**
     * @dataProvider ellipsizeProvider
     */
    public function testEllipsize(string $expected, string $value, int $length): void
    {
        $this->assertSame($expected, Str::ellipsize($value, $length));
    }

    /**
     * @return array<array{string,string,int}>
     */
    public static function ellipsizeProvider(): array
    {
        return [
            ['Hello...', 'Hello, world!', 8],
            ['Hello', 'Hello', 5],
            ['...', 'Hello', 3],
            ['...', 'Hello', 1],
            ['', '', 5],
        ];
    }

    /**
     * @dataProvider wordsProvider
     */
    public function testWords(
        ?string $expected,
        string $string,
        string $separator = ' ',
        string $preserve = '',
        ?Closure $callback = null
    ): void {
        if ($expected === null) {
            $this->expectException(InvalidArgumentException::class);
        }
        $this->assertSame($expected, Str::words($string, $separator, $preserve, $callback));
    }

    /**
     * @return array<array{string|null,string,2?:string,3?:string,4?:Closure|null}>
     */
    public static function wordsProvider(): array
    {
        return [
            ['foo bar', 'foo bar'],
            ['FOO BAR', 'FOO_BAR'],
            ['foo Bar', 'fooBar'],
            ['this foo Bar', '$this = fooBar'],
            ['this = foo Bar', '$this = fooBar', ' ', '='],
            ['this=foo Bar', '$this=fooBar', ' ', '='],
            ['PHP Doc', 'PHPDoc'],
            ['PHP8 Doc', 'PHP8Doc'],
            ['PHP8 DOC', 'PHP8DOC'],
            ['PHP8 doc', 'PHP8doc'],
            ['Php8 Doc', 'Php8Doc'],
            ['Php8 DOC', 'Php8DOC'],
            ['Php8doc', 'Php8doc'],
            ['php8 Doc', 'php8Doc'],
            ['php8 DOC', 'php8DOC'],
            ['php8doc', 'php8doc'],
            ['8 Doc', '8Doc'],
            ['8 DOC', '8DOC'],
            ['8doc', '8doc'],
            ['PHP8.3_0 Doc', 'PHP8.3_0Doc', ' ', '._'],
            ['foo\Bar', 'fooBar', '\\'],
            ['foo$Bar', 'fooBar', '$'],
            ['foo*Bar', '*fooBar*', '*'],
            ['foo Bar', '🚀fooBar🚀'],
            ['🚀foo Bar🚀', '🚀fooBar🚀', ' ', '🚀'],
            ['foo🚀Bar', '🚀fooBar🚀', '🚀'],
            [null, 'fooBar', '\\', '\\'],
            [null, 'fooBar', '$0'],
            [null, 'fooBar', 'T'],
            [null, '🚀fooBar🚀', '🚀', '🚀'],
        ];
    }

    /**
     * @dataProvider caseProvider
     */
    public function testCase(
        string $string,
        string $preserve,
        string $expectedSnakeCase,
        string $expectedKebabCase,
        string $expectedCamelCase,
        string $expectedPascalCase
    ): void {
        $name = Get::code([$string, $preserve]);
        $this->assertSame($expectedSnakeCase, Str::snake($string, $preserve), "snake_case{$name}");
        $this->assertSame($expectedKebabCase, Str::kebab($string, $preserve), "kebab-case{$name}");
        $this->assertSame($expectedCamelCase, Str::camel($string, $preserve), "camelCase{$name}");
        $this->assertSame($expectedPascalCase, Str::pascal($string, $preserve), "PascalCase{$name}");
    }

    /**
     * @return array<array{string,string,string,string,string,string}>
     */
    public static function caseProvider(): array
    {
        return [
            ['', '', '', '', '', ''],
            ['__', '', '', '', '', ''],
            ['0', '', '0', '0', '0', '0'],
            [' 0 ', '', '0', '0', '0', '0'],
            ['0.00', '', '0_00', '0-00', '000', '000'],
            ['2e5', '', '2e5', '2e5', '2e5', '2e5'],
            ['2.0e5', '', '2_0e5', '2-0e5', '20e5', '20e5'],
            ['**two words**', '', 'two_words', 'two-words', 'twoWords', 'TwoWords'],
            ['**Two_Words**', '', 'two_words', 'two-words', 'twoWords', 'TwoWords'],
            ['**TWO-WORDS**', '', 'two_words', 'two-words', 'twoWords', 'TwoWords'],
            ['**two.Words**', '', 'two_words', 'two-words', 'twoWords', 'TwoWords'],
            ['12two_words', '', '12two_words', '12two-words', '12twoWords', '12twoWords'],
            ['12two_Words', '', '12two_words', '12two-words', '12twoWords', '12twoWords'],
            ['12Two_Words', '', '12_two_words', '12-two-words', '12TwoWords', '12TwoWords'],
            ['12TWO_WORDS', '', '12_two_words', '12-two-words', '12TwoWords', '12TwoWords'],
            ['12twoWords', '', '12two_words', '12two-words', '12twoWords', '12twoWords'],
            ['12TwoWords', '', '12_two_words', '12-two-words', '12TwoWords', '12TwoWords'],
            ['12TWOWords', '', '12_two_words', '12-two-words', '12TwoWords', '12TwoWords'],
            ['field-name=value-name', '=', 'field_name=value_name', 'field-name=value-name', 'fieldName=valueName', 'FieldName=ValueName'],
            ['Field-name=Value-name', '=', 'field_name=value_name', 'field-name=value-name', 'fieldName=valueName', 'FieldName=ValueName'],
            ['Field-Name=Value-Name', '=', 'field_name=value_name', 'field-name=value-name', 'fieldName=valueName', 'FieldName=ValueName'],
            ['FIELD-NAME=VALUE-NAME', '=', 'field_name=value_name', 'field-name=value-name', 'fieldName=valueName', 'FieldName=ValueName'],
            ['two words', '', 'two_words', 'two-words', 'twoWords', 'TwoWords'],
            ['two_12words', '', 'two_12words', 'two-12words', 'two12words', 'Two12words'],
            ['two_12Words', '', 'two_12_words', 'two-12-words', 'two12Words', 'Two12Words'],
            ['Two_12Words', '', 'two_12_words', 'two-12-words', 'two12Words', 'Two12Words'],
            ['TWO_12WORDS', '', 'two_12_words', 'two-12-words', 'two12Words', 'Two12Words'],
            ['Two_Words', '', 'two_words', 'two-words', 'twoWords', 'TwoWords'],
            ['two_words12', '', 'two_words12', 'two-words12', 'twoWords12', 'TwoWords12'],
            ['two_Words12', '', 'two_words12', 'two-words12', 'twoWords12', 'TwoWords12'],
            ['Two_Words12', '', 'two_words12', 'two-words12', 'twoWords12', 'TwoWords12'],
            ['TWO_WORDS12', '', 'two_words12', 'two-words12', 'twoWords12', 'TwoWords12'],
            ['TWO-WORDS', '', 'two_words', 'two-words', 'twoWords', 'TwoWords'],
            ['two.Words', '', 'two_words', 'two-words', 'twoWords', 'TwoWords'],
            ['two12_words', '', 'two12_words', 'two12-words', 'two12Words', 'Two12Words'],
            ['two12_Words', '', 'two12_words', 'two12-words', 'two12Words', 'Two12Words'],
            ['Two12_Words', '', 'two12_words', 'two12-words', 'two12Words', 'Two12Words'],
            ['TWO12_WORDS', '', 'two12_words', 'two12-words', 'two12Words', 'Two12Words'],
            ['two12words', '', 'two12words', 'two12words', 'two12words', 'Two12words'],
            ['two12Words', '', 'two12_words', 'two12-words', 'two12Words', 'Two12Words'],
            ['Two12Words', '', 'two12_words', 'two12-words', 'two12Words', 'Two12Words'],
            ['TWO12WORDS', '', 'two12_words', 'two12-words', 'two12Words', 'Two12Words'],
            ['twoWords', '', 'two_words', 'two-words', 'twoWords', 'TwoWords'],
            ['TwoWords', '', 'two_words', 'two-words', 'twoWords', 'TwoWords'],
            ['TWOWords', '', 'two_words', 'two-words', 'twoWords', 'TwoWords'],
            ['twoWords12', '', 'two_words12', 'two-words12', 'twoWords12', 'TwoWords12'],
            ['TwoWords12', '', 'two_words12', 'two-words12', 'twoWords12', 'TwoWords12'],
            ['TWOWords12', '', 'two_words12', 'two-words12', 'twoWords12', 'TwoWords12'],
        ];
    }

    /**
     * @dataProvider expandTabsProvider
     */
    public function testExpandTabs(
        string $expected,
        string $text,
        int $tabSize = 8,
        int $column = 1
    ): void {
        $this->assertSame(
            $expected,
            Str::expandTabs($text, $tabSize, $column)
        );
    }

    /**
     * @return array<array{string,string,2?:int,3?:int}>
     */
    public static function expandTabsProvider(): array
    {
        return [
            ['', '', 4],
            ["\n", "\n", 4],
            ["\n\n", "\n\n", 4],
            ["\n    ", "\n\t", 4],
            ["\n    \n", "\n\t\n", 4],
            ['    ', "\t", 4],
            ['a   ', "a\t", 4],
            ['abcdef  ', "abcdef\t", 4],
            ['abc de  f       ', "abc\tde\tf\t\t", 4],
            ['   ', "\t", 4, 2],
            ['a ', "a\t", 4, 3],
            ['abcdef   ', "abcdef\t", 4, 4],
            ['abc   de  f       ', "abc\tde\tf\t\t", 4, 7],
            ['        ', "\t", 8],
            ['a       ', "a\t", 8],
            ['abcdef  ', "abcdef\t", 8],
            ['abc     de      f               ', "abc\tde\tf\t\t", 8],
            ["   \nabc ", "\t\nabc\t", 4, 2],
            [
                <<<EOF
    abc de  f       g
1   23  4
EOF,
                <<<EOF
\tabc\tde\tf\t\tg
1\t23\t4
EOF,
                4,
            ],
        ];
    }

    /**
     * @dataProvider expandLeadingTabsProvider
     */
    public function testExpandLeadingTabs(
        string $expected,
        string $text,
        int $tabSize = 8,
        bool $preserveLine1 = false,
        int $column = 1
    ): void {
        $this->assertSame(
            $expected,
            Str::expandLeadingTabs($text, $tabSize, $preserveLine1, $column)
        );
    }

    /**
     * @return array<array{string,string,2?:int,3?:bool,4?:int}>
     */
    public static function expandLeadingTabsProvider(): array
    {
        return [
            ['', '', 4],
            ["\n", "\n", 4],
            ["\n\n", "\n\n", 4],
            ["\n    ", "\n\t", 4],
            ["\n    \n", "\n\t\n", 4],
            ['    ', "\t", 4],
            ["a\t", "a\t", 4],
            ['    a', "\ta", 4],
            ["    a\t", "\ta\t", 4],
            ["abcdef\t", "abcdef\t", 4],
            ["    abc\tde\tf\t\t", "\tabc\tde\tf\t\t", 4],
            ['   ', "\t", 4, false, 2],
            ['  a', "\ta", 4, false, 3],
            [' abcdef', "\tabcdef", 4, false, 4],
            ["\tabcdef", "\tabcdef", 4, true, 4],
            ["  abc\tde\tf\t\t", "\tabc\tde\tf\t\t", 4, false, 7],
            ['        ', "\t", 8],
            ["a\t", "a\t", 8],
            ['        a', "\ta", 8],
            ["   \nabc\t", "\t\nabc\t", 4, false, 2],
            ["   \n    abc\t", "\t\n\tabc\t", 4, false, 2],
            ["   0\t1\t2\n    0\t1\t2", "\t0\t1\t2\n\t0\t1\t2", 4, false, 2],
            [
                <<<EOF
    abc\tde\tf\t\tg
1\t23\t4
EOF,
                <<<EOF
\tabc\tde\tf\t\tg
1\t23\t4
EOF,
                4,
            ],
        ];
    }

    /**
     * @dataProvider splitDelimitedProvider
     *
     * @param list<string> $expected
     * @param non-empty-string $separator
     * @param int-mask-of<Str::PRESERVE_*> $flags
     */
    public function testSplitDelimited(
        array $expected,
        string $separator,
        string $string,
        bool $removeEmpty = false,
        ?string $characters = null,
        int $flags = Str::PRESERVE_DOUBLE_QUOTED
    ): void {
        $this->assertSame($expected, Str::splitDelimited($separator, $string, $removeEmpty, $characters, $flags));
    }

    /**
     * @return array<array{list<string>,non-empty-string,string,3?:bool,4?:string|null,5?:int-mask-of<Str::PRESERVE_*>}>
     */
    public function splitDelimitedProvider(): array
    {
        return [
            [
                [],
                ',',
                '',
                true,
            ],
            [
                [''],
                ',',
                '',
            ],
            [
                ['apple', 'banana', 'cherry'],
                ',',
                'apple,banana,cherry',
            ],
            [
                ['apple', 'banana', 'cherry'],
                ',',
                'apple, banana, cherry',
            ],
            [
                ['apple', 'banana', 'cherry'],
                ',',
                ',,,apple,banana,,cherry,',
                true,
            ],
            [
                ['', '', '', 'apple', 'banana', '', 'cherry', ''],
                ',',
                ',,,apple,banana,,cherry,',
            ],
            [
                ['"apple, banana"', 'cherry'],
                ',',
                '"apple, banana", cherry',
            ],
            [
                ["'apple", "banana'", 'cherry'],
                ',',
                "'apple, banana', cherry",
            ],
            [
                ["'apple, banana'", 'cherry'],
                ',',
                "'apple, banana', cherry",
                false,
                null,
                Str::PRESERVE_QUOTED,
            ],
            [
                ["'apple, banana'", '"cherry, strawberry"'],
                ',',
                ' ,, \'apple, banana\' , , "cherry, strawberry" , ',
                true,
                null,
                Str::PRESERVE_QUOTED,
            ],
            [
                [' ', " 'apple, banana' ", ' ', ' "cherry, strawberry" ', ' '],
                ',',
                ' ,, \'apple, banana\' , , "cherry, strawberry" , ',
                true,
                '',
                Str::PRESERVE_QUOTED,
            ],
            [
                [' ', '', " 'apple", " banana' ", ' ', ' "cherry', ' strawberry" ', ' '],
                ',',
                ' ,, \'apple, banana\' , , "cherry, strawberry" , ',
                false,
                '',
                0,
            ],
            [
                ['{apple, (banana, ["mango, pear"])}', 'cherry'],
                ',',
                '{apple, (banana, ["mango, pear"])}, cherry',
            ],
        ];
    }

    public function testEnclose(): void
    {
        $this->assertEquals('**test**', Str::enclose('test', '**'));
        $this->assertEquals('[test]', Str::enclose('test', '[', ']'));
        $this->assertEquals('!!test?', Str::enclose('test', '!!', '?'));
    }

    /**
     * @dataProvider unwrapProvider
     */
    public function testUnwrap(
        string $expected,
        string $string,
        string $break = \PHP_EOL,
        bool $ignoreEscapes = true,
        bool $trimLines = false,
        bool $collapseBlankLines = false
    ): void {
        $this->assertSame(
            $expected,
            Str::unwrap($string, $break, $ignoreEscapes, $trimLines, $collapseBlankLines)
        );
    }

    /**
     * @return array<string,array{0:string,1:string,2?:string,3?:bool,4?:bool,5?:bool}>
     */
    public static function unwrapProvider(): array
    {
        return [
            'empty' => [
                <<<'EOF'
EOF,
                <<<'EOF'
EOF,
            ],
            'unwrapped' => [
                <<<'EOF'
Tempor in mollit ad esse.
EOF,
                <<<'EOF'
Tempor in mollit ad esse.
EOF,
            ],
            'paragraph + list + indent' => [
                <<<'EOF'
Tempor pariatur nulla esse velit esse:
- Ad officia ex   reprehenderit sint et.
- Ea occaecat et aliqua ea officia cupidatat ad nulla cillum.
- Proident ullamco id eu id.

Amet duis aliqua qui laboris ullamco dolor nostrud irure commodo ad eu anim enim.

    Cillum adipisicing sit cillum
    sunt elit magna fugiat do in
    deserunt ut Lorem aliqua.


EOF,
                <<<'EOF'
Tempor pariatur nulla
esse velit esse:
- Ad officia ex
  reprehenderit sint et.
- Ea occaecat et aliqua ea officia
cupidatat ad nulla cillum.
- Proident ullamco id eu id.

Amet duis aliqua qui laboris
ullamco dolor nostrud irure
commodo ad eu anim enim.

    Cillum adipisicing sit cillum
    sunt elit magna fugiat do in
    deserunt ut Lorem aliqua.


EOF,
            ],
            'leading + trailing + inner lines' => [
                <<<'EOF'

Est   esse sunt velit ea.

EOF,
                <<<'EOF'

Est   esse
sunt velit
ea.

EOF,
            ],
            'escaped #1' => [
                <<<'EOF'
Nisi aliqua id in cupidatat\ consectetur irure ad nisi Lorem non ea reprehenderit id eu.
EOF,
                <<<'EOF'
Nisi aliqua id in cupidatat\
consectetur irure ad nisi
Lorem non ea reprehenderit id eu.
EOF,
            ],
            'escaped #2' => [
                <<<'EOF'
Nisi aliqua id in cupidatat\
consectetur irure ad nisi Lorem non ea reprehenderit id eu.
EOF,
                <<<'EOF'
Nisi aliqua id in cupidatat\
consectetur irure ad nisi
Lorem non ea reprehenderit id eu.
EOF,
                \PHP_EOL,
                false,
            ],
            'trimmed #1 (baseline)' => [
                <<<'EOF'
Est magna\   voluptate   minim est.
- Item Line 2 Line 3
    Quote
    Line 2

 

Qui\ exercitation elit.
1. Item 1    Line 2    Line 3
2. Item 2    Line 2

EOF,
                <<<'EOF'
Est magna\ 
 voluptate 
 minim est.
- Item
Line 2
Line 3
    Quote
    Line 2

 

Qui\
exercitation
elit.
1. Item 1
   Line 2
   Line 3
2. Item 2
   Line 2

EOF,
                \PHP_EOL,
            ],
            'trimmed #2 (+ trimLines)' => [
                <<<'EOF'
Est magna\ voluptate minim est.
- Item Line 2 Line 3
    Quote
    Line 2



Qui\ exercitation elit.
1. Item 1 Line 2 Line 3
2. Item 2 Line 2

EOF,
                <<<'EOF'
Est magna\ 
 voluptate 
 minim est.
- Item
Line 2
Line 3
    Quote
    Line 2

 

Qui\
exercitation
elit.
1. Item 1
   Line 2
   Line 3
2. Item 2
   Line 2

EOF,
                \PHP_EOL,
                true,
                true,
            ],
            'trimmed #3 (- ignoreEscapes, + trimLines)' => [
                <<<'EOF'
Est magna\  voluptate minim est.
- Item Line 2 Line 3
    Quote
    Line 2



Qui\
exercitation elit.
1. Item 1 Line 2 Line 3
2. Item 2 Line 2

EOF,
                <<<'EOF'
Est magna\ 
 voluptate 
 minim est.
- Item
Line 2
Line 3
    Quote
    Line 2

 

Qui\
exercitation
elit.
1. Item 1
   Line 2
   Line 3
2. Item 2
   Line 2

EOF,
                \PHP_EOL,
                false,
                true,
            ],
            'trimmed #4 (- ignoreEscapes, + trimLines, + collapseBlankLines)' => [
                <<<'EOF'
Est magna\  voluptate minim est.
- Item Line 2 Line 3
    Quote
    Line 2

Qui\
exercitation elit.
1. Item 1 Line 2 Line 3
2. Item 2 Line 2

EOF,
                <<<'EOF'
Est magna\ 
 voluptate 
 minim est.
- Item
Line 2
Line 3
    Quote
    Line 2

 

Qui\
exercitation
elit.
1. Item 1
   Line 2
   Line 3
2. Item 2
   Line 2

EOF,
                \PHP_EOL,
                false,
                true,
                true,
            ],
            'trimmed #5 (- ignoreEscapes, + collapseBlankLines)' => [
                <<<'EOF'
Est magna\   voluptate   minim est.
- Item Line 2 Line 3
    Quote
    Line 2

 

Qui\
exercitation elit.
1. Item 1    Line 2    Line 3
2. Item 2    Line 2

EOF,
                <<<'EOF'
Est magna\ 
 voluptate 
 minim est.
- Item
Line 2
Line 3
    Quote
    Line 2

 

Qui\
exercitation
elit.
1. Item 1
   Line 2
   Line 3
2. Item 2
   Line 2

EOF,
                \PHP_EOL,
                false,
                false,
                true,
            ],
        ];
    }

    /**
     * @dataProvider distanceProvider
     */
    public function testDistance(
        float $expected,
        string $string1,
        string $string2,
        bool $normalise = true
    ): void {
        $this->assertSame($expected, Str::distance($string1, $string2, $normalise));
    }

    /**
     * @return array<array{float,string,string,3?:bool}>
     */
    public static function distanceProvider(): array
    {
        return [
            [0.0, '', ''],
            [1.0, 'DELIVERY', 'milk delivery', false],
            [0.5, 'DELIVERY', 'milk deliverer'],
            [0.38461538461538464, 'DELIVERY', 'milk delivery'],
            [0.7692307692307693, 'DELIVERY - MILK', 'milk delivery'],
            [0.0, 'DELIVERY', 'delivery'],
            [0.6190476190476191, 'DELIVERY', 'milk delivery service'],
        ];
    }

    /**
     * @dataProvider similarityProvider
     */
    public function testSimilarity(
        float $expected,
        string $string1,
        string $string2,
        bool $normalise = true
    ): void {
        $this->assertSame($expected, Str::similarity($string1, $string2, $normalise));
    }

    /**
     * @return array<array{float,string,string,3?:bool}>
     */
    public static function similarityProvider(): array
    {
        return [
            [1.0, '', ''],
            [0.0, 'DELIVERY', 'milk delivery', false],
            [0.5, 'DELIVERY', 'milk deliverer'],
            [0.6153846153846154, 'DELIVERY', 'milk delivery'],
            [0.6153846153846154, 'DELIVERY - MILK', 'milk delivery'],
            [1.0, 'DELIVERY - MILK', 'delivery milk'],
            [1.0, 'DELIVERY', 'delivery'],
            [0.38095238095238093, 'DELIVERY', 'milk delivery service'],
        ];
    }

    /**
     * @dataProvider ngramSimilarityProvider
     */
    public function testNgramSimilarity(
        float $expected,
        string $string1,
        string $string2,
        bool $normalise = true,
        int $size = 2
    ): void {
        $this->assertSame($expected, Str::ngramSimilarity($string1, $string2, $normalise, $size));
    }

    /**
     * @return array<array{float,string,string,3?:bool,4?:int}>
     */
    public static function ngramSimilarityProvider(): array
    {
        return [
            [1.0, '', ''],
            [0.0, 'DELIVERY', 'milk delivery', false],
            [0.46153846153846156, 'DELIVERY', 'milk deliverer'],
            [0.5833333333333334, 'DELIVERY', 'milk delivery'],
            [0.8333333333333334, 'DELIVERY - MILK', 'milk delivery'],
            [1.0, 'DELIVERY - MILK', 'delivery milk'],
            [1.0, 'DELIVERY', 'delivery'],
            [0.35, 'DELIVERY', 'milk delivery service'],
            [0.4166666666666667, 'DELIVERY', 'milk deliverer', true, 3],
            [0.5454545454545454, 'DELIVERY', 'milk delivery', true, 3],
            [0.7272727272727273, 'DELIVERY - MILK', 'milk delivery', true, 3],
            [1.0, 'DELIVERY - MILK', 'delivery milk', true, 3],
            [1.0, 'DELIVERY', 'delivery', true, 3],
            [0.3157894736842105, 'DELIVERY', 'milk delivery service', true, 3],
        ];
    }

    /**
     * @dataProvider ngramIntersectionProvider
     */
    public function testNgramIntersection(
        float $expected,
        string $string1,
        string $string2,
        bool $normalise = true,
        int $size = 2
    ): void {
        $this->assertSame($expected, Str::ngramIntersection($string1, $string2, $normalise, $size));
    }

    /**
     * @return array<array{float,string,string,3?:bool,4?:int}>
     */
    public static function ngramIntersectionProvider(): array
    {
        return [
            [0.0, 'DELIVERY', 'milk delivery', false],
            [0.8571428571428571, 'DELIVERY', 'milk deliverer'],
            [1.0, 'DELIVERY', 'milk delivery'],
            [0.8333333333333334, 'DELIVERY - MILK', 'milk delivery'],
            [1.0, 'DELIVERY - MILK', 'delivery milk'],
            [1.0, 'DELIVERY', 'delivery'],
            [1.0, 'DELIVERY', 'milk delivery service'],
            [0.8333333333333334, 'DELIVERY', 'milk deliverer', true, 3],
            [1.0, 'DELIVERY', 'milk delivery', true, 3],
            [0.7272727272727273, 'DELIVERY - MILK', 'milk delivery', true, 3],
            [1.0, 'DELIVERY - MILK', 'delivery milk', true, 3],
            [1.0, 'DELIVERY', 'delivery', true, 3],
            [1.0, 'DELIVERY', 'milk delivery service', true, 3],
        ];
    }

    /**
     * @dataProvider ngramsProvider
     *
     * @param string[] $expected
     */
    public function testNgrams(
        array $expected,
        string $string,
        int $size = 2
    ): void {
        $actual = Str::ngrams($string, $size);
        sort($actual);
        $this->assertSame($expected, $actual);
    }

    /**
     * @return array<array{string[],string,2?:int}>
     */
    public static function ngramsProvider(): array
    {
        return [
            [
                [],
                '',
            ],
            [
                [],
                'a',
            ],
            [
                ['ab'],
                'ab',
            ],
            [
                ['ab', 'bc'],
                'abc',
            ],
            [
                ['ab', 'bc', 'cd'],
                'abcd',
            ],
            [
                ['ab', 'bc', 'cd', 'de'],
                'abcde',
            ],
            [
                ['ab', 'bc', 'cd', 'de', 'ef'],
                'abcdef',
            ],
            [
                ['abc', 'bcd', 'cde'],
                'abcde',
                3,
            ],
        ];
    }

    /**
     * @dataProvider mergeListsProvider
     */
    public function testMergeLists(
        string $expected,
        string $text,
        string $separator = "\n",
        ?string $marker = null,
        ?string $regex = null,
        bool $clean = false,
        bool $loose = false
    ): void {
        if ($regex === null) {
            /** @var string */
            $regex = (
                new ReflectionParameter([Str::class, 'mergeLists'], 'regex')
            )->getDefaultValue();
        }
        $this->assertSame($expected, Str::eolToNative(Str::mergeLists($text, $separator, $marker, $regex, $clean, $loose)));
    }

    /**
     * @return array<string,array{string,string,2?:string,3?:string|null,4?:string|null,5?:bool,6?:bool}>
     */
    public static function mergeListsProvider(): array
    {
        $input1 = <<<EOF
- Before lists

Section:
- d
Other section:
- <not a letter>
Without a subsequent list
Section:
- a
- b
Section:
- c
- b
- d
EOF;

        $input2 = <<<EOF
- Before lists
📍 Section:
- list item
- another

Other section:
- item i
- item ii

- Standalone

Also standalone

Section:
- another
- and another
EOF;

        $input3 = <<<EOF
### Changes

- Description

- Description
  over
  multiple

  ```
  lines
  ```

### Changes

- Description
  with different details

- Description
  over
  multiple

  ```
  lines
  ```


EOF;

        $input4 = <<<EOF
- Description
- Description
  over
  multiple

  ```
  lines
  ```
- Description
  with different details
- Description
  over
  multiple

  ```
  lines
  ```
EOF;

        return [
            'Default' => [
                <<<EOF
- Before lists
Without a subsequent list
Section:
- d
- a
- b
- c
Other section:
- <not a letter>
EOF,
                $input1,
            ],
            'Default (clean)' => [
                <<<EOF
Before lists
Without a subsequent list
Section:
- d
- a
- b
- c
Other section:
- <not a letter>
EOF,
                $input1,
                "\n",
                null,
                null,
                true,
            ],
            'Markdown' => [
                <<<EOF
- Before lists

Without a subsequent list

Section:

- d
- a
- b
- c

Other section:

- <not a letter>
EOF,
                $input1,
                "\n\n",
            ],
            'Nested' => [
                <<<EOF
- Before lists

- Without a subsequent list

- Section:

  - d
  - a
  - b
  - c

- Other section:

  - <not a letter>
EOF,
                $input1,
                "\n\n",
                '-',
            ],
            'Default (multibyte)' => [
                <<<EOF
- Before lists
- Standalone
📍 Also standalone
📍 Section:
  - list item
  - another
  - and another
📍 Other section:
  - item i
  - item ii
EOF,
                $input2,
                "\n",
                '📍',
            ],
            'Default (multibyte, clean)' => [
                <<<EOF
📍 Before lists
📍 Standalone
📍 Also standalone
📍 Section:
  - list item
  - another
  - and another
📍 Other section:
  - item i
  - item ii
EOF,
                $input2,
                "\n",
                '📍',
                null,
                true,
            ],
            'Markdown (multibyte)' => [
                <<<EOF
- Before lists
- Standalone

📍 Also standalone

📍 Section:

  - list item
  - another
  - and another

📍 Other section:

  - item i
  - item ii
EOF,
                $input2,
                "\n\n",
                '📍',
            ],
            'Markdown (multiline #1, loose)' => [
                <<<EOF
### Changes

- Description
- Description
  over
  multiple

  ```
  lines
  ```
- Description
  with different details
EOF,
                $input3,
                "\n\n",
                null,
                null,
                false,
                true,
            ],
            'Markdown (multiline #1, not loose)' => [
                <<<EOF
- Description
  over
  multiple

  ```
  lines
  ```

### Changes

- Description
- Description
  with different details
EOF,
                $input3,
                "\n\n",
            ],
            'Markdown (multiline #2)' => [
                <<<EOF
- Description
- Description
  over
  multiple

  ```
  lines
  ```
- Description
  with different details
EOF,
                $input4,
                "\n\n",
            ],
        ];
    }
}
