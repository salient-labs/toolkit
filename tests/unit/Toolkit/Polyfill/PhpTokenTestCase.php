<?php declare(strict_types=1);

namespace Salient\Tests\Polyfill;

use Salient\Polyfill\PhpToken;
use Salient\Tests\TestCase;
use Salient\Utility\Get;
use Salient\Utility\Str;
use Throwable;
use TypeError;

abstract class PhpTokenTestCase extends TestCase
{
    private const CODE = <<<'PHP'
<div><?php
class A
{
    const PUBLIC = 'public';

    /**
     * comment #1
     */
    function f(int $a): string
    {
        // comment #2
        return sprintf('0x%02x', $a);
    }

    // comment #3
}

$b = f(77);
echo "Value: {$b}";
?></div>
PHP;

    /** @var class-string<PhpToken|\PhpToken> */
    protected static string $Token;

    /**
     * @dataProvider getTokenNameProvider
     *
     * @param array<string|null> $expected
     * @param array<PhpToken|\PhpToken> $input
     */
    public function testGetTokenName(array $expected, array $input): void
    {
        foreach ($input as $token) {
            $actual[] = $token->getTokenName();
        }
        $actualCode = Get::code($actual ?? []);

        $this->assertSame(
            $expected,
            $actual ?? [],
            'If $input changed, replace $expected with: ' . $actualCode,
        );
    }

    /**
     * @return array<array{array<string|null>,array<PhpToken|\PhpToken>}>
     */
    public static function getTokenNameProvider(): array
    {
        return [
            [
                [
                    'T_INLINE_HTML',
                    'T_OPEN_TAG',
                    'T_CLASS',
                    'T_WHITESPACE',
                    'T_STRING',
                    'T_WHITESPACE',
                    '{',
                    'T_WHITESPACE',
                    'T_CONST',
                    'T_WHITESPACE',
                    'T_STRING',
                    'T_WHITESPACE',
                    '=',
                    'T_WHITESPACE',
                    'T_CONSTANT_ENCAPSED_STRING',
                    ';',
                    'T_WHITESPACE',
                    'T_DOC_COMMENT',
                    'T_WHITESPACE',
                    'T_FUNCTION',
                    'T_WHITESPACE',
                    'T_STRING',
                    '(',
                    'T_STRING',
                    'T_WHITESPACE',
                    'T_VARIABLE',
                    ')',
                    ':',
                    'T_WHITESPACE',
                    'T_STRING',
                    'T_WHITESPACE',
                    '{',
                    'T_WHITESPACE',
                    'T_COMMENT',
                    'T_WHITESPACE',
                    'T_RETURN',
                    'T_WHITESPACE',
                    'T_STRING',
                    '(',
                    'T_CONSTANT_ENCAPSED_STRING',
                    ',',
                    'T_WHITESPACE',
                    'T_VARIABLE',
                    ')',
                    ';',
                    'T_WHITESPACE',
                    '}',
                    'T_WHITESPACE',
                    'T_COMMENT',
                    'T_WHITESPACE',
                    '}',
                    'T_WHITESPACE',
                    'T_VARIABLE',
                    'T_WHITESPACE',
                    '=',
                    'T_WHITESPACE',
                    'T_STRING',
                    '(',
                    'T_LNUMBER',
                    ')',
                    ';',
                    'T_WHITESPACE',
                    'T_ECHO',
                    'T_WHITESPACE',
                    '"',
                    'T_ENCAPSED_AND_WHITESPACE',
                    'T_CURLY_OPEN',
                    'T_VARIABLE',
                    '}',
                    '"',
                    ';',
                    'T_WHITESPACE',
                    'T_CLOSE_TAG',
                    'T_INLINE_HTML',
                ],
                static::$Token::tokenize(self::CODE, \TOKEN_PARSE),
            ],
            [
                [
                    null,
                ],
                [
                    new static::$Token(10001, ''),
                ],
            ],
        ];
    }

    /**
     * @dataProvider isProvider
     *
     * @param bool[]|class-string<Throwable> $expected
     * @param array<PhpToken|\PhpToken> $input
     * @param int|string|array<int|string> $kind
     */
    public function testIs($expected, array $input, $kind): void
    {
        if (is_string($expected)) {
            $this->expectException($expected);
        }

        foreach ($input as $token) {
            $actual[] = $token->is($kind);
        }
        $actualCode = Get::code($actual ?? []);

        $this->assertSame(
            $expected,
            $actual ?? [],
            'If $input changed, replace $expected with: ' . $actualCode,
        );
    }

    /**
     * @return array<array{bool[]|class-string<Throwable>,array<PhpToken|\PhpToken>,mixed}>
     */
    public static function isProvider(): array
    {
        return [
            [
                [
                    false,
                    true,
                    false,
                    true,
                    false,
                    true,
                    false,
                    true,
                    false,
                    true,
                    false,
                    true,
                    false,
                    true,
                    false,
                    false,
                    true,
                    true,
                    true,
                    false,
                    true,
                    false,
                    false,
                    false,
                    true,
                    true,
                    false,
                    false,
                    true,
                    false,
                    true,
                    false,
                    true,
                    true,
                    true,
                    false,
                    true,
                    false,
                    false,
                    false,
                    false,
                    true,
                    true,
                    false,
                    false,
                    true,
                    false,
                    true,
                    true,
                    true,
                    false,
                    true,
                    false,
                    true,
                    false,
                    true,
                    false,
                    false,
                    false,
                    false,
                    false,
                    true,
                    false,
                    true,
                    false,
                    false,
                    false,
                    false,
                    false,
                    false,
                    false,
                    true,
                    false,
                    false,
                ],
                static::$Token::tokenize(self::CODE, \TOKEN_PARSE),
                [
                    \T_WHITESPACE,
                    \T_COMMENT,
                    \T_DOC_COMMENT,
                    \T_OPEN_TAG,
                    '$a',
                ],
            ],
            [
                [
                    false,
                    false,
                    false,
                    false,
                    false,
                    false,
                    false,
                    false,
                    true,
                    false,
                    false,
                    false,
                    false,
                    false,
                    false,
                    false,
                    false,
                    false,
                    false,
                    false,
                    false,
                    false,
                    false,
                    false,
                    false,
                    false,
                    false,
                    false,
                    false,
                    false,
                    false,
                    false,
                    false,
                    false,
                    false,
                    false,
                    false,
                    false,
                    false,
                    false,
                    false,
                    false,
                    false,
                    false,
                    false,
                    false,
                    false,
                    false,
                    false,
                    false,
                    false,
                    false,
                    false,
                    false,
                    false,
                    false,
                    false,
                    false,
                    false,
                    false,
                    false,
                    false,
                    false,
                    false,
                    false,
                    false,
                    false,
                    false,
                    false,
                    false,
                    false,
                    false,
                    false,
                    false,
                ],
                static::$Token::tokenize(self::CODE, \TOKEN_PARSE),
                \T_CONST,
            ],
            [
                [
                    false,
                    false,
                    false,
                    false,
                    false,
                    false,
                    false,
                    false,
                    false,
                    false,
                    false,
                    false,
                    false,
                    false,
                    false,
                    false,
                    false,
                    false,
                    false,
                    false,
                    false,
                    false,
                    false,
                    false,
                    false,
                    true,
                    false,
                    false,
                    false,
                    false,
                    false,
                    false,
                    false,
                    false,
                    false,
                    false,
                    false,
                    false,
                    false,
                    false,
                    false,
                    false,
                    true,
                    false,
                    false,
                    false,
                    false,
                    false,
                    false,
                    false,
                    false,
                    false,
                    false,
                    false,
                    false,
                    false,
                    false,
                    false,
                    false,
                    false,
                    false,
                    false,
                    false,
                    false,
                    false,
                    false,
                    false,
                    false,
                    false,
                    false,
                    false,
                    false,
                    false,
                    false,
                ],
                static::$Token::tokenize(self::CODE, \TOKEN_PARSE),
                '$a',
            ],
            [
                TypeError::class,
                static::$Token::tokenize(self::CODE, \TOKEN_PARSE),
                false,
            ],
            [
                TypeError::class,
                static::$Token::tokenize(self::CODE, \TOKEN_PARSE),
                [
                    \T_OPEN_TAG,
                    '$a',
                    false,
                ],
            ],
            [
                TypeError::class,
                [
                    new static::$Token(\T_OPEN_TAG, "<?php\n"),
                    new static::$Token(\T_VARIABLE, '$a'),
                ],
                [
                    false,
                    \T_OPEN_TAG,
                    '$a',
                ],
            ],
            [
                [
                    true,
                    true,
                ],
                [
                    new static::$Token(\T_OPEN_TAG, "<?php\n"),
                    new static::$Token(\T_VARIABLE, '$a'),
                ],
                [
                    \T_OPEN_TAG,
                    '$a',
                    false,
                ],
            ],
        ];
    }

    /**
     * @dataProvider isIgnorableProvider
     *
     * @param bool[] $expected
     * @param array<PhpToken|\PhpToken> $input
     */
    public function testIsIgnorable(array $expected, array $input): void
    {
        foreach ($input as $token) {
            $actual[] = $token->isIgnorable();
        }
        $actualCode = Get::code($actual ?? []);

        $this->assertSame(
            $expected,
            $actual ?? [],
            'If $input changed, replace $expected with: ' . $actualCode,
        );
    }

    /**
     * @return array<array{bool[],array<PhpToken|\PhpToken>}>
     */
    public static function isIgnorableProvider(): array
    {
        return [
            [
                [
                    false,
                    true,
                    false,
                    true,
                    false,
                    true,
                    false,
                    true,
                    false,
                    true,
                    false,
                    true,
                    false,
                    true,
                    false,
                    false,
                    true,
                    true,
                    true,
                    false,
                    true,
                    false,
                    false,
                    false,
                    true,
                    false,
                    false,
                    false,
                    true,
                    false,
                    true,
                    false,
                    true,
                    true,
                    true,
                    false,
                    true,
                    false,
                    false,
                    false,
                    false,
                    true,
                    false,
                    false,
                    false,
                    true,
                    false,
                    true,
                    true,
                    true,
                    false,
                    true,
                    false,
                    true,
                    false,
                    true,
                    false,
                    false,
                    false,
                    false,
                    false,
                    true,
                    false,
                    true,
                    false,
                    false,
                    false,
                    false,
                    false,
                    false,
                    false,
                    true,
                    false,
                    false,
                ],
                static::$Token::tokenize(self::CODE, \TOKEN_PARSE),
            ],
        ];
    }

    public function testToString(): void
    {
        $code = '';
        foreach (static::$Token::tokenize(self::CODE, \TOKEN_PARSE) as $token) {
            $code .= (string) $token;
        }
        $this->assertSame(self::CODE, $code);
    }

    /**
     * @dataProvider tokenizeProvider
     *
     * @param array<PhpToken|\PhpToken> $expected
     */
    public function testTokenize(array $expected, string $input, int $flags = 0): void
    {
        $actual = static::$Token::tokenize(Str::setEol($input), $flags);
        $actualCode = array_reduce(
            $actual,
            fn($code, $token) => $code . sprintf(
                "    new static::\$Token(%s, %s, %d, %d),\n",
                $token->id < 128 ? $token->id : '\\' . $token->getTokenName(),
                Get::code($token->text),
                $token->line,
                $token->pos,
            ),
            "[\n"
        ) . ']';

        $this->assertEquals(
            $expected,
            $actual,
            'If $input changed, replace $expected with: ' . $actualCode,
        );
    }

    /**
     * @return array<array{array<PhpToken|\PhpToken>,string,2?:int}>
     */
    public static function tokenizeProvider(): array
    {
        return [
            'with TOKEN_PARSE' => [
                [
                    new static::$Token(\T_INLINE_HTML, '<div>', 1, 0),
                    new static::$Token(\T_OPEN_TAG, "<?php\n", 1, 5),
                    new static::$Token(\T_CLASS, 'class', 2, 11),
                    new static::$Token(\T_WHITESPACE, ' ', 2, 16),
                    new static::$Token(\T_STRING, 'A', 2, 17),
                    new static::$Token(\T_WHITESPACE, "\n", 2, 18),
                    new static::$Token(123, '{', 3, 19),
                    new static::$Token(\T_WHITESPACE, "\n    ", 3, 20),
                    new static::$Token(\T_CONST, 'const', 4, 25),
                    new static::$Token(\T_WHITESPACE, ' ', 4, 30),
                    new static::$Token(\T_STRING, 'PUBLIC', 4, 31),
                    new static::$Token(\T_WHITESPACE, ' ', 4, 37),
                    new static::$Token(61, '=', 4, 38),
                    new static::$Token(\T_WHITESPACE, ' ', 4, 39),
                    new static::$Token(\T_CONSTANT_ENCAPSED_STRING, "'public'", 4, 40),
                    new static::$Token(59, ';', 4, 48),
                    new static::$Token(\T_WHITESPACE, "\n\n    ", 4, 49),
                    new static::$Token(\T_DOC_COMMENT, "/**\n     * comment #1\n     */", 6, 55),
                    new static::$Token(\T_WHITESPACE, "\n    ", 8, 84),
                    new static::$Token(\T_FUNCTION, 'function', 9, 89),
                    new static::$Token(\T_WHITESPACE, ' ', 9, 97),
                    new static::$Token(\T_STRING, 'f', 9, 98),
                    new static::$Token(40, '(', 9, 99),
                    new static::$Token(\T_STRING, 'int', 9, 100),
                    new static::$Token(\T_WHITESPACE, ' ', 9, 103),
                    new static::$Token(\T_VARIABLE, '$a', 9, 104),
                    new static::$Token(41, ')', 9, 106),
                    new static::$Token(58, ':', 9, 107),
                    new static::$Token(\T_WHITESPACE, ' ', 9, 108),
                    new static::$Token(\T_STRING, 'string', 9, 109),
                    new static::$Token(\T_WHITESPACE, "\n    ", 9, 115),
                    new static::$Token(123, '{', 10, 120),
                    new static::$Token(\T_WHITESPACE, "\n        ", 10, 121),
                    new static::$Token(\T_COMMENT, '// comment #2', 11, 130),
                    new static::$Token(\T_WHITESPACE, "\n        ", 11, 143),
                    new static::$Token(\T_RETURN, 'return', 12, 152),
                    new static::$Token(\T_WHITESPACE, ' ', 12, 158),
                    new static::$Token(\T_STRING, 'sprintf', 12, 159),
                    new static::$Token(40, '(', 12, 166),
                    new static::$Token(\T_CONSTANT_ENCAPSED_STRING, "'0x%02x'", 12, 167),
                    new static::$Token(44, ',', 12, 175),
                    new static::$Token(\T_WHITESPACE, ' ', 12, 176),
                    new static::$Token(\T_VARIABLE, '$a', 12, 177),
                    new static::$Token(41, ')', 12, 179),
                    new static::$Token(59, ';', 12, 180),
                    new static::$Token(\T_WHITESPACE, "\n    ", 12, 181),
                    new static::$Token(125, '}', 13, 186),
                    new static::$Token(\T_WHITESPACE, "\n\n    ", 13, 187),
                    new static::$Token(\T_COMMENT, '// comment #3', 15, 193),
                    new static::$Token(\T_WHITESPACE, "\n", 15, 206),
                    new static::$Token(125, '}', 16, 207),
                    new static::$Token(\T_WHITESPACE, "\n\n", 16, 208),
                    new static::$Token(\T_VARIABLE, '$b', 18, 210),
                    new static::$Token(\T_WHITESPACE, ' ', 18, 212),
                    new static::$Token(61, '=', 18, 213),
                    new static::$Token(\T_WHITESPACE, ' ', 18, 214),
                    new static::$Token(\T_STRING, 'f', 18, 215),
                    new static::$Token(40, '(', 18, 216),
                    new static::$Token(\T_LNUMBER, '77', 18, 217),
                    new static::$Token(41, ')', 18, 219),
                    new static::$Token(59, ';', 18, 220),
                    new static::$Token(\T_WHITESPACE, "\n", 18, 221),
                    new static::$Token(\T_ECHO, 'echo', 19, 222),
                    new static::$Token(\T_WHITESPACE, ' ', 19, 226),
                    new static::$Token(34, '"', 19, 227),
                    new static::$Token(\T_ENCAPSED_AND_WHITESPACE, 'Value: ', 19, 228),
                    new static::$Token(\T_CURLY_OPEN, '{', 19, 235),
                    new static::$Token(\T_VARIABLE, '$b', 19, 236),
                    new static::$Token(125, '}', 19, 238),
                    new static::$Token(34, '"', 19, 239),
                    new static::$Token(59, ';', 19, 240),
                    new static::$Token(\T_WHITESPACE, "\n", 19, 241),
                    new static::$Token(\T_CLOSE_TAG, '?>', 20, 242),
                    new static::$Token(\T_INLINE_HTML, '</div>', 20, 244),
                ],
                self::CODE,
                \TOKEN_PARSE,
            ],
            'without TOKEN_PARSE' => [
                [
                    new static::$Token(\T_INLINE_HTML, '<div>', 1, 0),
                    new static::$Token(\T_OPEN_TAG, "<?php\n", 1, 5),
                    new static::$Token(\T_CLASS, 'class', 2, 11),
                    new static::$Token(\T_WHITESPACE, ' ', 2, 16),
                    new static::$Token(\T_STRING, 'A', 2, 17),
                    new static::$Token(\T_WHITESPACE, "\n", 2, 18),
                    new static::$Token(123, '{', 3, 19),
                    new static::$Token(\T_WHITESPACE, "\n    ", 3, 20),
                    new static::$Token(\T_CONST, 'const', 4, 25),
                    new static::$Token(\T_WHITESPACE, ' ', 4, 30),
                    new static::$Token(\T_PUBLIC, 'PUBLIC', 4, 31),
                    new static::$Token(\T_WHITESPACE, ' ', 4, 37),
                    new static::$Token(61, '=', 4, 38),
                    new static::$Token(\T_WHITESPACE, ' ', 4, 39),
                    new static::$Token(\T_CONSTANT_ENCAPSED_STRING, "'public'", 4, 40),
                    new static::$Token(59, ';', 4, 48),
                    new static::$Token(\T_WHITESPACE, "\n\n    ", 4, 49),
                    new static::$Token(\T_DOC_COMMENT, "/**\n     * comment #1\n     */", 6, 55),
                    new static::$Token(\T_WHITESPACE, "\n    ", 8, 84),
                    new static::$Token(\T_FUNCTION, 'function', 9, 89),
                    new static::$Token(\T_WHITESPACE, ' ', 9, 97),
                    new static::$Token(\T_STRING, 'f', 9, 98),
                    new static::$Token(40, '(', 9, 99),
                    new static::$Token(\T_STRING, 'int', 9, 100),
                    new static::$Token(\T_WHITESPACE, ' ', 9, 103),
                    new static::$Token(\T_VARIABLE, '$a', 9, 104),
                    new static::$Token(41, ')', 9, 106),
                    new static::$Token(58, ':', 9, 107),
                    new static::$Token(\T_WHITESPACE, ' ', 9, 108),
                    new static::$Token(\T_STRING, 'string', 9, 109),
                    new static::$Token(\T_WHITESPACE, "\n    ", 9, 115),
                    new static::$Token(123, '{', 10, 120),
                    new static::$Token(\T_WHITESPACE, "\n        ", 10, 121),
                    new static::$Token(\T_COMMENT, '// comment #2', 11, 130),
                    new static::$Token(\T_WHITESPACE, "\n        ", 11, 143),
                    new static::$Token(\T_RETURN, 'return', 12, 152),
                    new static::$Token(\T_WHITESPACE, ' ', 12, 158),
                    new static::$Token(\T_STRING, 'sprintf', 12, 159),
                    new static::$Token(40, '(', 12, 166),
                    new static::$Token(\T_CONSTANT_ENCAPSED_STRING, "'0x%02x'", 12, 167),
                    new static::$Token(44, ',', 12, 175),
                    new static::$Token(\T_WHITESPACE, ' ', 12, 176),
                    new static::$Token(\T_VARIABLE, '$a', 12, 177),
                    new static::$Token(41, ')', 12, 179),
                    new static::$Token(59, ';', 12, 180),
                    new static::$Token(\T_WHITESPACE, "\n    ", 12, 181),
                    new static::$Token(125, '}', 13, 186),
                    new static::$Token(\T_WHITESPACE, "\n\n    ", 13, 187),
                    new static::$Token(\T_COMMENT, '// comment #3', 15, 193),
                    new static::$Token(\T_WHITESPACE, "\n", 15, 206),
                    new static::$Token(125, '}', 16, 207),
                    new static::$Token(\T_WHITESPACE, "\n\n", 16, 208),
                    new static::$Token(\T_VARIABLE, '$b', 18, 210),
                    new static::$Token(\T_WHITESPACE, ' ', 18, 212),
                    new static::$Token(61, '=', 18, 213),
                    new static::$Token(\T_WHITESPACE, ' ', 18, 214),
                    new static::$Token(\T_STRING, 'f', 18, 215),
                    new static::$Token(40, '(', 18, 216),
                    new static::$Token(\T_LNUMBER, '77', 18, 217),
                    new static::$Token(41, ')', 18, 219),
                    new static::$Token(59, ';', 18, 220),
                    new static::$Token(\T_WHITESPACE, "\n", 18, 221),
                    new static::$Token(\T_ECHO, 'echo', 19, 222),
                    new static::$Token(\T_WHITESPACE, ' ', 19, 226),
                    new static::$Token(34, '"', 19, 227),
                    new static::$Token(\T_ENCAPSED_AND_WHITESPACE, 'Value: ', 19, 228),
                    new static::$Token(\T_CURLY_OPEN, '{', 19, 235),
                    new static::$Token(\T_VARIABLE, '$b', 19, 236),
                    new static::$Token(125, '}', 19, 238),
                    new static::$Token(34, '"', 19, 239),
                    new static::$Token(59, ';', 19, 240),
                    new static::$Token(\T_WHITESPACE, "\n", 19, 241),
                    new static::$Token(\T_CLOSE_TAG, '?>', 20, 242),
                    new static::$Token(\T_INLINE_HTML, '</div>', 20, 244),
                ],
                self::CODE,
            ],
        ];
    }
}
