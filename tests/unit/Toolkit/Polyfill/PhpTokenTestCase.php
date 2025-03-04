<?php declare(strict_types=1);

namespace Salient\Tests\Polyfill;

use Salient\Polyfill\PhpToken;
use Salient\Tests\TestCase;
use Salient\Utility\Get;
use Salient\Utility\Str;
use TypeError;

abstract class PhpTokenTestCase extends TestCase
{
    private const CODE = <<<'PHP'
<div><?php
namespace Foo\Bar;

use Foo\{A as AA, B AS BB};

class A
{
    const PUBLIC = 1;

    /**
     * comment
     */
    public function foo(int $a): string
    {
        // comment
        return \sprintf('0x%02x', $a);
    }


}

$a = new namespace\A();
echo b"public{$a->foo(77)}";
?></div>
PHP;

    /**
     * @dataProvider getTokenNameProvider
     *
     * @param array<string|null> $expected
     * @param array<PhpToken|\PhpToken> $tokens
     */
    public function testGetTokenName(array $expected, array $tokens): void
    {
        $actual = [];
        foreach ($tokens as $token) {
            $actual[] = $token->getTokenName();
        }
        $this->assertSame($expected, $actual, self::getMessage($actual));
    }

    /**
     * @return array<array{array<string|null>,array<PhpToken|\PhpToken>}>
     */
    public static function getTokenNameProvider(): array
    {
        $token = static::getToken();
        return [
            [
                [
                    'T_INLINE_HTML',
                    'T_OPEN_TAG',
                    'T_NAMESPACE',
                    'T_WHITESPACE',
                    'T_NAME_QUALIFIED',
                    ';',
                    'T_WHITESPACE',
                    'T_USE',
                    'T_WHITESPACE',
                    'T_STRING',
                    'T_NS_SEPARATOR',
                    '{',
                    'T_STRING',
                    'T_WHITESPACE',
                    'T_AS',
                    'T_WHITESPACE',
                    'T_STRING',
                    ',',
                    'T_WHITESPACE',
                    'T_STRING',
                    'T_WHITESPACE',
                    'T_AS',
                    'T_WHITESPACE',
                    'T_STRING',
                    '}',
                    ';',
                    'T_WHITESPACE',
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
                    'T_LNUMBER',
                    ';',
                    'T_WHITESPACE',
                    'T_DOC_COMMENT',
                    'T_WHITESPACE',
                    'T_PUBLIC',
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
                    'T_NAME_FULLY_QUALIFIED',
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
                    '}',
                    'T_WHITESPACE',
                    'T_VARIABLE',
                    'T_WHITESPACE',
                    '=',
                    'T_WHITESPACE',
                    'T_NEW',
                    'T_WHITESPACE',
                    'T_NAME_RELATIVE',
                    '(',
                    ')',
                    ';',
                    'T_WHITESPACE',
                    'T_ECHO',
                    'T_WHITESPACE',
                    '"',
                    'T_ENCAPSED_AND_WHITESPACE',
                    'T_CURLY_OPEN',
                    'T_VARIABLE',
                    'T_OBJECT_OPERATOR',
                    'T_STRING',
                    '(',
                    'T_LNUMBER',
                    ')',
                    '}',
                    '"',
                    ';',
                    'T_WHITESPACE',
                    'T_CLOSE_TAG',
                    'T_INLINE_HTML',
                ],
                $token::tokenize(self::CODE, \TOKEN_PARSE),
            ],
            [
                [
                    null,
                ],
                [
                    new $token(max(
                        10001,
                        \T_NAME_FULLY_QUALIFIED,
                        \T_NAME_QUALIFIED,
                        \T_NAME_RELATIVE,
                        \T_ATTRIBUTE,
                        \T_MATCH,
                        \T_NULLSAFE_OBJECT_OPERATOR,
                        \T_AMPERSAND_FOLLOWED_BY_VAR_OR_VARARG,
                        \T_AMPERSAND_NOT_FOLLOWED_BY_VAR_OR_VARARG,
                        \T_ENUM,
                        \T_READONLY,
                        \T_PRIVATE_SET,
                        \T_PROTECTED_SET,
                        \T_PUBLIC_SET,
                        \T_PROPERTY_C,
                    ) + 1, ''),
                ],
            ],
        ];
    }

    /**
     * @dataProvider isProvider
     *
     * @param bool[]|string $expected
     * @param array<PhpToken|\PhpToken> $tokens
     * @param int|string|array<int|string> $kind
     */
    public function testIs($expected, array $tokens, $kind): void
    {
        $this->maybeExpectException($expected);
        $actual = [];
        foreach ($tokens as $token) {
            $actual[] = $token->is($kind);
        }
        $actual = array_filter($actual);
        $this->assertSame($expected, $actual, self::getMessage($actual));
    }

    /**
     * @return array<array{bool[]|string,array<PhpToken|\PhpToken>,mixed}>
     */
    public static function isProvider(): array
    {
        $token = static::getToken();
        return [
            [
                [
                    1 => true,
                    3 => true,
                    6 => true,
                    8 => true,
                    13 => true,
                    15 => true,
                    18 => true,
                    20 => true,
                    22 => true,
                    26 => true,
                    28 => true,
                    30 => true,
                    32 => true,
                    34 => true,
                    36 => true,
                    38 => true,
                    41 => true,
                    42 => true,
                    43 => true,
                    45 => true,
                    47 => true,
                    51 => true,
                    52 => true,
                    55 => true,
                    57 => true,
                    59 => true,
                    60 => true,
                    61 => true,
                    63 => true,
                    68 => true,
                    69 => true,
                    72 => true,
                    74 => true,
                    76 => true,
                    77 => true,
                    78 => true,
                    80 => true,
                    82 => true,
                    87 => true,
                    89 => true,
                    93 => true,
                    102 => true,
                ],
                $token::tokenize(self::CODE, \TOKEN_PARSE),
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
                    33 => true,
                ],
                $token::tokenize(self::CODE, \TOKEN_PARSE),
                \T_CONST,
            ],
            [
                [
                    52 => true,
                    69 => true,
                    77 => true,
                    93 => true,
                ],
                $token::tokenize(self::CODE, \TOKEN_PARSE),
                '$a',
            ],
            [
                TypeError::class,
                $token::tokenize(self::CODE, \TOKEN_PARSE),
                false,
            ],
            [
                TypeError::class,
                $token::tokenize(self::CODE, \TOKEN_PARSE),
                [
                    \T_OPEN_TAG,
                    '$a',
                    false,
                ],
            ],
            [
                TypeError::class,
                [
                    new $token(\T_OPEN_TAG, "<?php\n"),
                    new $token(\T_VARIABLE, '$a'),
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
                    new $token(\T_OPEN_TAG, "<?php\n"),
                    new $token(\T_VARIABLE, '$a'),
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
     * @param array<PhpToken|\PhpToken> $tokens
     */
    public function testIsIgnorable(array $expected, array $tokens): void
    {
        $actual = [];
        foreach ($tokens as $token) {
            $actual[] = $token->isIgnorable();
        }
        $actual = array_filter($actual);
        $this->assertSame($expected, $actual, self::getMessage($actual));
    }

    /**
     * @return array<array{bool[],array<PhpToken|\PhpToken>}>
     */
    public static function isIgnorableProvider(): array
    {
        $token = static::getToken();
        return [
            [
                [
                    1 => true,
                    3 => true,
                    6 => true,
                    8 => true,
                    13 => true,
                    15 => true,
                    18 => true,
                    20 => true,
                    22 => true,
                    26 => true,
                    28 => true,
                    30 => true,
                    32 => true,
                    34 => true,
                    36 => true,
                    38 => true,
                    41 => true,
                    42 => true,
                    43 => true,
                    45 => true,
                    47 => true,
                    51 => true,
                    55 => true,
                    57 => true,
                    59 => true,
                    60 => true,
                    61 => true,
                    63 => true,
                    68 => true,
                    72 => true,
                    74 => true,
                    76 => true,
                    78 => true,
                    80 => true,
                    82 => true,
                    87 => true,
                    89 => true,
                    102 => true,
                ],
                $token::tokenize(self::CODE, \TOKEN_PARSE),
            ],
        ];
    }

    public function testToString(): void
    {
        $code = '';
        foreach (static::getToken()::tokenize(self::CODE, \TOKEN_PARSE) as $token) {
            $code .= (string) $token;
        }
        $this->assertSame(self::CODE, $code);
    }

    /**
     * @dataProvider tokenizeProvider
     *
     * @param array<PhpToken|\PhpToken> $expected
     */
    public function testTokenize(array $expected, string $code, int $flags = 0): void
    {
        $actual = static::getToken()::tokenize(Str::setEol($code), $flags);
        $actualCode = "[\n";
        foreach ($actual as $token) {
            $actualCode .= sprintf(
                "    new \$token(%s, %s, %d, %d),\n",
                $token->id < 256 ? $token->id : '\\' . $token->getTokenName(),
                Get::code($token->text),
                $token->line,
                $token->pos,
            );
        }
        $actualCode .= ']';
        $this->assertEquals(
            $expected,
            $actual,
            'If $code changed, replace $expected with: ' . $actualCode,
        );
    }

    /**
     * @return array<array{array<PhpToken|\PhpToken>,string,2?:int}>
     */
    public static function tokenizeProvider(): array
    {
        $token = static::getToken();
        return [
            'with TOKEN_PARSE' => [
                [
                    new $token(\T_INLINE_HTML, '<div>', 1, 0),
                    new $token(\T_OPEN_TAG, "<?php\n", 1, 5),
                    new $token(\T_NAMESPACE, 'namespace', 2, 11),
                    new $token(\T_WHITESPACE, ' ', 2, 20),
                    new $token(\T_NAME_QUALIFIED, 'Foo\Bar', 2, 21),
                    new $token(59, ';', 2, 28),
                    new $token(\T_WHITESPACE, "\n\n", 2, 29),
                    new $token(\T_USE, 'use', 4, 31),
                    new $token(\T_WHITESPACE, ' ', 4, 34),
                    new $token(\T_STRING, 'Foo', 4, 35),
                    new $token(\T_NS_SEPARATOR, '\\', 4, 38),
                    new $token(123, '{', 4, 39),
                    new $token(\T_STRING, 'A', 4, 40),
                    new $token(\T_WHITESPACE, ' ', 4, 41),
                    new $token(\T_AS, 'as', 4, 42),
                    new $token(\T_WHITESPACE, ' ', 4, 44),
                    new $token(\T_STRING, 'AA', 4, 45),
                    new $token(44, ',', 4, 47),
                    new $token(\T_WHITESPACE, ' ', 4, 48),
                    new $token(\T_STRING, 'B', 4, 49),
                    new $token(\T_WHITESPACE, ' ', 4, 50),
                    new $token(\T_AS, 'AS', 4, 51),
                    new $token(\T_WHITESPACE, ' ', 4, 53),
                    new $token(\T_STRING, 'BB', 4, 54),
                    new $token(125, '}', 4, 56),
                    new $token(59, ';', 4, 57),
                    new $token(\T_WHITESPACE, "\n\n", 4, 58),
                    new $token(\T_CLASS, 'class', 6, 60),
                    new $token(\T_WHITESPACE, ' ', 6, 65),
                    new $token(\T_STRING, 'A', 6, 66),
                    new $token(\T_WHITESPACE, "\n", 6, 67),
                    new $token(123, '{', 7, 68),
                    new $token(\T_WHITESPACE, "\n    ", 7, 69),
                    new $token(\T_CONST, 'const', 8, 74),
                    new $token(\T_WHITESPACE, ' ', 8, 79),
                    new $token(\T_STRING, 'PUBLIC', 8, 80),
                    new $token(\T_WHITESPACE, ' ', 8, 86),
                    new $token(61, '=', 8, 87),
                    new $token(\T_WHITESPACE, ' ', 8, 88),
                    new $token(\T_LNUMBER, '1', 8, 89),
                    new $token(59, ';', 8, 90),
                    new $token(\T_WHITESPACE, "\n\n    ", 8, 91),
                    new $token(\T_DOC_COMMENT, "/**\n     * comment\n     */", 10, 97),
                    new $token(\T_WHITESPACE, "\n    ", 12, 123),
                    new $token(\T_PUBLIC, 'public', 13, 128),
                    new $token(\T_WHITESPACE, ' ', 13, 134),
                    new $token(\T_FUNCTION, 'function', 13, 135),
                    new $token(\T_WHITESPACE, ' ', 13, 143),
                    new $token(\T_STRING, 'foo', 13, 144),
                    new $token(40, '(', 13, 147),
                    new $token(\T_STRING, 'int', 13, 148),
                    new $token(\T_WHITESPACE, ' ', 13, 151),
                    new $token(\T_VARIABLE, '$a', 13, 152),
                    new $token(41, ')', 13, 154),
                    new $token(58, ':', 13, 155),
                    new $token(\T_WHITESPACE, ' ', 13, 156),
                    new $token(\T_STRING, 'string', 13, 157),
                    new $token(\T_WHITESPACE, "\n    ", 13, 163),
                    new $token(123, '{', 14, 168),
                    new $token(\T_WHITESPACE, "\n        ", 14, 169),
                    new $token(\T_COMMENT, '// comment', 15, 178),
                    new $token(\T_WHITESPACE, "\n        ", 15, 188),
                    new $token(\T_RETURN, 'return', 16, 197),
                    new $token(\T_WHITESPACE, ' ', 16, 203),
                    new $token(\T_NAME_FULLY_QUALIFIED, '\sprintf', 16, 204),
                    new $token(40, '(', 16, 212),
                    new $token(\T_CONSTANT_ENCAPSED_STRING, "'0x%02x'", 16, 213),
                    new $token(44, ',', 16, 221),
                    new $token(\T_WHITESPACE, ' ', 16, 222),
                    new $token(\T_VARIABLE, '$a', 16, 223),
                    new $token(41, ')', 16, 225),
                    new $token(59, ';', 16, 226),
                    new $token(\T_WHITESPACE, "\n    ", 16, 227),
                    new $token(125, '}', 17, 232),
                    new $token(\T_WHITESPACE, "\n\n\n", 17, 233),
                    new $token(125, '}', 20, 236),
                    new $token(\T_WHITESPACE, "\n\n", 20, 237),
                    new $token(\T_VARIABLE, '$a', 22, 239),
                    new $token(\T_WHITESPACE, ' ', 22, 241),
                    new $token(61, '=', 22, 242),
                    new $token(\T_WHITESPACE, ' ', 22, 243),
                    new $token(\T_NEW, 'new', 22, 244),
                    new $token(\T_WHITESPACE, ' ', 22, 247),
                    new $token(\T_NAME_RELATIVE, 'namespace\A', 22, 248),
                    new $token(40, '(', 22, 259),
                    new $token(41, ')', 22, 260),
                    new $token(59, ';', 22, 261),
                    new $token(\T_WHITESPACE, "\n", 22, 262),
                    new $token(\T_ECHO, 'echo', 23, 263),
                    new $token(\T_WHITESPACE, ' ', 23, 267),
                    new $token(34, 'b"', 23, 268),
                    new $token(\T_ENCAPSED_AND_WHITESPACE, 'public', 23, 270),
                    new $token(\T_CURLY_OPEN, '{', 23, 276),
                    new $token(\T_VARIABLE, '$a', 23, 277),
                    new $token(\T_OBJECT_OPERATOR, '->', 23, 279),
                    new $token(\T_STRING, 'foo', 23, 281),
                    new $token(40, '(', 23, 284),
                    new $token(\T_LNUMBER, '77', 23, 285),
                    new $token(41, ')', 23, 287),
                    new $token(125, '}', 23, 288),
                    new $token(34, '"', 23, 289),
                    new $token(59, ';', 23, 290),
                    new $token(\T_WHITESPACE, "\n", 23, 291),
                    new $token(\T_CLOSE_TAG, '?>', 24, 292),
                    new $token(\T_INLINE_HTML, '</div>', 24, 294),
                ],
                self::CODE,
                \TOKEN_PARSE,
            ],
            'without TOKEN_PARSE' => [
                [
                    new $token(\T_INLINE_HTML, '<div>', 1, 0),
                    new $token(\T_OPEN_TAG, "<?php\n", 1, 5),
                    new $token(\T_NAMESPACE, 'namespace', 2, 11),
                    new $token(\T_WHITESPACE, ' ', 2, 20),
                    new $token(\T_NAME_QUALIFIED, 'Foo\Bar', 2, 21),
                    new $token(59, ';', 2, 28),
                    new $token(\T_WHITESPACE, "\n\n", 2, 29),
                    new $token(\T_USE, 'use', 4, 31),
                    new $token(\T_WHITESPACE, ' ', 4, 34),
                    new $token(\T_STRING, 'Foo', 4, 35),
                    new $token(\T_NS_SEPARATOR, '\\', 4, 38),
                    new $token(123, '{', 4, 39),
                    new $token(\T_STRING, 'A', 4, 40),
                    new $token(\T_WHITESPACE, ' ', 4, 41),
                    new $token(\T_AS, 'as', 4, 42),
                    new $token(\T_WHITESPACE, ' ', 4, 44),
                    new $token(\T_STRING, 'AA', 4, 45),
                    new $token(44, ',', 4, 47),
                    new $token(\T_WHITESPACE, ' ', 4, 48),
                    new $token(\T_STRING, 'B', 4, 49),
                    new $token(\T_WHITESPACE, ' ', 4, 50),
                    new $token(\T_AS, 'AS', 4, 51),
                    new $token(\T_WHITESPACE, ' ', 4, 53),
                    new $token(\T_STRING, 'BB', 4, 54),
                    new $token(125, '}', 4, 56),
                    new $token(59, ';', 4, 57),
                    new $token(\T_WHITESPACE, "\n\n", 4, 58),
                    new $token(\T_CLASS, 'class', 6, 60),
                    new $token(\T_WHITESPACE, ' ', 6, 65),
                    new $token(\T_STRING, 'A', 6, 66),
                    new $token(\T_WHITESPACE, "\n", 6, 67),
                    new $token(123, '{', 7, 68),
                    new $token(\T_WHITESPACE, "\n    ", 7, 69),
                    new $token(\T_CONST, 'const', 8, 74),
                    new $token(\T_WHITESPACE, ' ', 8, 79),
                    new $token(\T_PUBLIC, 'PUBLIC', 8, 80),
                    new $token(\T_WHITESPACE, ' ', 8, 86),
                    new $token(61, '=', 8, 87),
                    new $token(\T_WHITESPACE, ' ', 8, 88),
                    new $token(\T_LNUMBER, '1', 8, 89),
                    new $token(59, ';', 8, 90),
                    new $token(\T_WHITESPACE, "\n\n    ", 8, 91),
                    new $token(\T_DOC_COMMENT, "/**\n     * comment\n     */", 10, 97),
                    new $token(\T_WHITESPACE, "\n    ", 12, 123),
                    new $token(\T_PUBLIC, 'public', 13, 128),
                    new $token(\T_WHITESPACE, ' ', 13, 134),
                    new $token(\T_FUNCTION, 'function', 13, 135),
                    new $token(\T_WHITESPACE, ' ', 13, 143),
                    new $token(\T_STRING, 'foo', 13, 144),
                    new $token(40, '(', 13, 147),
                    new $token(\T_STRING, 'int', 13, 148),
                    new $token(\T_WHITESPACE, ' ', 13, 151),
                    new $token(\T_VARIABLE, '$a', 13, 152),
                    new $token(41, ')', 13, 154),
                    new $token(58, ':', 13, 155),
                    new $token(\T_WHITESPACE, ' ', 13, 156),
                    new $token(\T_STRING, 'string', 13, 157),
                    new $token(\T_WHITESPACE, "\n    ", 13, 163),
                    new $token(123, '{', 14, 168),
                    new $token(\T_WHITESPACE, "\n        ", 14, 169),
                    new $token(\T_COMMENT, '// comment', 15, 178),
                    new $token(\T_WHITESPACE, "\n        ", 15, 188),
                    new $token(\T_RETURN, 'return', 16, 197),
                    new $token(\T_WHITESPACE, ' ', 16, 203),
                    new $token(\T_NAME_FULLY_QUALIFIED, '\sprintf', 16, 204),
                    new $token(40, '(', 16, 212),
                    new $token(\T_CONSTANT_ENCAPSED_STRING, "'0x%02x'", 16, 213),
                    new $token(44, ',', 16, 221),
                    new $token(\T_WHITESPACE, ' ', 16, 222),
                    new $token(\T_VARIABLE, '$a', 16, 223),
                    new $token(41, ')', 16, 225),
                    new $token(59, ';', 16, 226),
                    new $token(\T_WHITESPACE, "\n    ", 16, 227),
                    new $token(125, '}', 17, 232),
                    new $token(\T_WHITESPACE, "\n\n\n", 17, 233),
                    new $token(125, '}', 20, 236),
                    new $token(\T_WHITESPACE, "\n\n", 20, 237),
                    new $token(\T_VARIABLE, '$a', 22, 239),
                    new $token(\T_WHITESPACE, ' ', 22, 241),
                    new $token(61, '=', 22, 242),
                    new $token(\T_WHITESPACE, ' ', 22, 243),
                    new $token(\T_NEW, 'new', 22, 244),
                    new $token(\T_WHITESPACE, ' ', 22, 247),
                    new $token(\T_NAME_RELATIVE, 'namespace\A', 22, 248),
                    new $token(40, '(', 22, 259),
                    new $token(41, ')', 22, 260),
                    new $token(59, ';', 22, 261),
                    new $token(\T_WHITESPACE, "\n", 22, 262),
                    new $token(\T_ECHO, 'echo', 23, 263),
                    new $token(\T_WHITESPACE, ' ', 23, 267),
                    new $token(34, 'b"', 23, 268),
                    new $token(\T_ENCAPSED_AND_WHITESPACE, 'public', 23, 270),
                    new $token(\T_CURLY_OPEN, '{', 23, 276),
                    new $token(\T_VARIABLE, '$a', 23, 277),
                    new $token(\T_OBJECT_OPERATOR, '->', 23, 279),
                    new $token(\T_STRING, 'foo', 23, 281),
                    new $token(40, '(', 23, 284),
                    new $token(\T_LNUMBER, '77', 23, 285),
                    new $token(41, ')', 23, 287),
                    new $token(125, '}', 23, 288),
                    new $token(34, '"', 23, 289),
                    new $token(59, ';', 23, 290),
                    new $token(\T_WHITESPACE, "\n", 23, 291),
                    new $token(\T_CLOSE_TAG, '?>', 24, 292),
                    new $token(\T_INLINE_HTML, '</div>', 24, 294),
                ],
                self::CODE,
            ],
        ];
    }

    /**
     * @return class-string<PhpToken|\PhpToken>
     */
    abstract protected static function getToken(): string;

    /**
     * @param mixed $actual
     */
    private static function getMessage($actual, string $valueName = '$tokens', string $expectedName = '$expected'): string
    {
        return sprintf(
            'If %s changed, replace %s with: %s',
            $valueName,
            $expectedName,
            Get::code($actual),
        );
    }
}
