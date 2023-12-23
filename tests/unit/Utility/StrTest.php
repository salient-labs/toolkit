<?php declare(strict_types=1);

namespace Lkrms\Tests\Utility;

use Lkrms\Tests\TestCase;
use Lkrms\Utility\Convert;
use Lkrms\Utility\Str;

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
            [
                null,
                null,
            ],
            [
                '',
                '',
            ],
            [
                null,
                '',
                null,
            ],
            [
                '',
                null,
                '',
            ],
            [
                'a',
                '',
                null,
                'a',
                null,
            ],
            [
                '0',
                '0',
                '1',
                null,
            ],
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
            [
                'foo bar',
                'foo bar',
            ],
            [
                'FOO BAR',
                'FOO_BAR',
            ],
            [
                'foo Bar',
                'fooBar',
            ],
            [
                'this foo Bar',
                '$this = fooBar',
            ],
            [
                'this = foo Bar',
                '$this = fooBar',
                ' ',
                '=',
            ],
            [
                'this=foo Bar',
                '$this=fooBar',
                ' ',
                '=',
            ],
            [
                'PHP Doc',
                'PHPDoc',
            ],
            [
                'PHP8 Doc',
                'PHP8Doc',
            ],
            [
                'PHP8.3_0 Doc',
                'PHP8.3_0Doc',
                ' ',
                '._',
            ],
        ];
    }

    /**
     * @dataProvider caseConversionProvider
     */
    public function testCaseConversion(
        string $string,
        ?string $preserve,
        string $expectedSnakeCase,
        string $expectedKebabCase,
        string $expectedCamelCase,
        string $expectedPascalCase
    ): void {
        $name = Convert::valueToCode([$string, $preserve]);
        $this->assertSame($expectedSnakeCase, Str::toSnakeCase($string, $preserve), "snake_case{$name}");
        $this->assertSame($expectedKebabCase, Str::toKebabCase($string, $preserve), "kebab-case{$name}");
        $this->assertSame($expectedCamelCase, Str::toCamelCase($string, $preserve), "camelCase{$name}");
        $this->assertSame($expectedPascalCase, Str::toPascalCase($string, $preserve), "PascalCase{$name}");
    }

    /**
     * @return array<array<string|null>>
     */
    public static function caseConversionProvider(): array
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
}
