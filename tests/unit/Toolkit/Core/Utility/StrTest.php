<?php declare(strict_types=1);

namespace Salient\Tests\Core\Utility;

use Salient\Core\Utility\Get;
use Salient\Core\Utility\Str;
use Salient\Tests\TestCase;
use ReflectionParameter;

/**
 * @covers \Salient\Core\Utility\Str
 */
final class StrTest extends TestCase
{
    /**
     * @dataProvider coalesceProvider
     */
    public function testCoalesce(?string $expected, ?string $string, ?string ...$strings): void
    {
        $this->assertSame($expected, Str::coalesce($string, ...$strings));
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
     * @dataProvider eolToNativeProvider
     */
    public function testEolToNative(?string $expected, ?string $string): void
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
                null,
                null,
            ],
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
    public function testEolFromNative(?string $expected, ?string $string): void
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
                null,
                null,
            ],
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
            ['HISTORY AND GEOGRAPHY', 'History & Geography'],
            ['MATHEMATICS', '& Mathematics'],
            ['LANGUAGES MODERN', 'Languages — Modern'],
            ['IT', 'I.T.'],
            ['IT', 'IT. '],
            ['IT', 'it'],
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
     * @dataProvider toWordsProvider
     */
    public function testToWords(
        string $expected,
        string $string,
        string $separator = ' ',
        ?string $preserve = null,
        ?callable $callback = null
    ): void {
        $this->assertSame($expected, Str::toWords($string, $separator, $preserve, $callback));
    }

    /**
     * @return array<array{string,string,2?:string,3?:string|null,4?:callable|null}>
     */
    public static function toWordsProvider(): array
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
            ['PHP8.3_0 Doc', 'PHP8.3_0Doc', ' ', '._'],
        ];
    }

    /**
     * @dataProvider toCaseProvider
     */
    public function testToCase(
        string $string,
        ?string $preserve,
        string $expectedSnakeCase,
        string $expectedKebabCase,
        string $expectedCamelCase,
        string $expectedPascalCase
    ): void {
        $name = Get::code([$string, $preserve]);
        $this->assertSame($expectedSnakeCase, Str::toSnakeCase($string, $preserve), "snake_case{$name}");
        $this->assertSame($expectedKebabCase, Str::toKebabCase($string, $preserve), "kebab-case{$name}");
        $this->assertSame($expectedCamelCase, Str::toCamelCase($string, $preserve), "camelCase{$name}");
        $this->assertSame($expectedPascalCase, Str::toPascalCase($string, $preserve), "PascalCase{$name}");
    }

    /**
     * @return array<array{string,string|null,string,string,string,string}>
     */
    public static function toCaseProvider(): array
    {
        return [
            ['**two words**', null, 'two_words', 'two-words', 'twoWords', 'TwoWords'],
            ['**Two_Words**', null, 'two_words', 'two-words', 'twoWords', 'TwoWords'],
            ['**TWO-WORDS**', null, 'two_words', 'two-words', 'twoWords', 'TwoWords'],
            ['**two.Words**', null, 'two_words', 'two-words', 'twoWords', 'TwoWords'],
            ['12two_words', null, '12two_words', '12two-words', '12twoWords', '12twoWords'],
            ['12two_Words', null, '12two_words', '12two-words', '12twoWords', '12twoWords'],
            ['12Two_Words', null, '12_two_words', '12-two-words', '12TwoWords', '12TwoWords'],
            ['12TWO_WORDS', null, '12_two_words', '12-two-words', '12TwoWords', '12TwoWords'],
            ['12twoWords', null, '12two_words', '12two-words', '12twoWords', '12twoWords'],
            ['12TwoWords', null, '12_two_words', '12-two-words', '12TwoWords', '12TwoWords'],
            ['12TWOWords', null, '12_two_words', '12-two-words', '12TwoWords', '12TwoWords'],
            ['field-name=value-name', '=', 'field_name=value_name', 'field-name=value-name', 'fieldName=valueName', 'FieldName=ValueName'],
            ['Field-name=Value-name', '=', 'field_name=value_name', 'field-name=value-name', 'fieldName=valueName', 'FieldName=ValueName'],
            ['Field-Name=Value-Name', '=', 'field_name=value_name', 'field-name=value-name', 'fieldName=valueName', 'FieldName=ValueName'],
            ['FIELD-NAME=VALUE-NAME', '=', 'field_name=value_name', 'field-name=value-name', 'fieldName=valueName', 'FieldName=ValueName'],
            ['two words', null, 'two_words', 'two-words', 'twoWords', 'TwoWords'],
            ['two_12words', null, 'two_12words', 'two-12words', 'two12words', 'Two12words'],
            ['two_12Words', null, 'two_12_words', 'two-12-words', 'two12Words', 'Two12Words'],
            ['Two_12Words', null, 'two_12_words', 'two-12-words', 'two12Words', 'Two12Words'],
            ['TWO_12WORDS', null, 'two_12_words', 'two-12-words', 'two12Words', 'Two12Words'],
            ['Two_Words', null, 'two_words', 'two-words', 'twoWords', 'TwoWords'],
            ['two_words12', null, 'two_words12', 'two-words12', 'twoWords12', 'TwoWords12'],
            ['two_Words12', null, 'two_words12', 'two-words12', 'twoWords12', 'TwoWords12'],
            ['Two_Words12', null, 'two_words12', 'two-words12', 'twoWords12', 'TwoWords12'],
            ['TWO_WORDS12', null, 'two_words12', 'two-words12', 'twoWords12', 'TwoWords12'],
            ['TWO-WORDS', null, 'two_words', 'two-words', 'twoWords', 'TwoWords'],
            ['two.Words', null, 'two_words', 'two-words', 'twoWords', 'TwoWords'],
            ['two12_words', null, 'two12_words', 'two12-words', 'two12Words', 'Two12Words'],
            ['two12_Words', null, 'two12_words', 'two12-words', 'two12Words', 'Two12Words'],
            ['Two12_Words', null, 'two12_words', 'two12-words', 'two12Words', 'Two12Words'],
            ['TWO12_WORDS', null, 'two12_words', 'two12-words', 'two12Words', 'Two12Words'],
            ['two12words', null, 'two12words', 'two12words', 'two12words', 'Two12words'],
            ['two12Words', null, 'two12_words', 'two12-words', 'two12Words', 'Two12Words'],
            ['Two12Words', null, 'two12_words', 'two12-words', 'two12Words', 'Two12Words'],
            ['TWO12WORDS', null, 'two12_words', 'two12-words', 'two12Words', 'Two12Words'],
            ['twoWords', null, 'two_words', 'two-words', 'twoWords', 'TwoWords'],
            ['TwoWords', null, 'two_words', 'two-words', 'twoWords', 'TwoWords'],
            ['TWOWords', null, 'two_words', 'two-words', 'twoWords', 'TwoWords'],
            ['twoWords12', null, 'two_words12', 'two-words12', 'twoWords12', 'TwoWords12'],
            ['TwoWords12', null, 'two_words12', 'two-words12', 'twoWords12', 'TwoWords12'],
            ['TWOWords12', null, 'two_words12', 'two-words12', 'twoWords12', 'TwoWords12'],
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
     * @dataProvider unwrapProvider
     */
    public function testUnwrap(
        string $expected,
        string $string,
        string $break = \PHP_EOL,
        bool $ignoreEscapes = true,
        bool $trimTrailingWhitespace = false,
        bool $collapseBlankLines = false
    ): void {
        $this->assertSame(
            $expected,
            Str::unwrap($string, $break, $ignoreEscapes, $trimTrailingWhitespace, $collapseBlankLines)
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
                Est magna\  voluptate  minim est.

                 


                EOF,
                <<<'EOF'
                Est magna\ 
                voluptate 
                minim est.

                 


                EOF,
                \PHP_EOL,
            ],
            'trimmed #2 (+ trimTrailingWhitespace)' => [
                <<<'EOF'
                Est magna\ voluptate minim est.




                EOF,
                <<<'EOF'
                Est magna\ 
                voluptate 
                minim est.

                 


                EOF,
                \PHP_EOL,
                true,
                true,
            ],
            'trimmed #3 (- ignoreEscapes)' => [
                <<<'EOF'
                Est magna\  voluptate minim est.




                EOF,
                <<<'EOF'
                Est magna\ 
                voluptate 
                minim est.

                 


                EOF,
                \PHP_EOL,
                false,
                true,
            ],
            'trimmed #4 (+ collapseBlankLines)' => [
                <<<'EOF'
                Est magna\  voluptate minim est.


                EOF,
                <<<'EOF'
                Est magna\ 
                voluptate 
                minim est.

                 


                EOF,
                \PHP_EOL,
                false,
                true,
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
     * @dataProvider ngramProvider
     *
     * @param string[] $expected
     */
    public function testNgram(
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
    public static function ngramProvider(): array
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
     *
     * @param mixed ...$args
     */
    public function testMergeLists(string $expected, ...$args): void
    {
        $this->assertSame($expected, Str::eolToNative(Str::mergeLists(...$args)));
    }

    /**
     * @return array<string,string[]>
     */
    public static function mergeListsProvider(): array
    {
        $defaultRegex = (
            new ReflectionParameter([Str::class, 'mergeLists'], 'regex')
        )->getDefaultValue();

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
                $defaultRegex,
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
