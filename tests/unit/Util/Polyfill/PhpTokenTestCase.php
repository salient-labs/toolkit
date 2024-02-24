<?php declare(strict_types=1);

namespace Lkrms\Tests\Polyfill;

use Lkrms\Polyfill\PhpToken;
use Salient\Core\Utility\Get;
use Salient\Core\Utility\Str;
use Salient\Tests\TestCase;
use Throwable;
use TypeError;

abstract class PhpTokenTestCase extends TestCase
{
    private const CODE = <<<'PHP'
        <div><?php
        class A
        {
            const PUBLIC = 'public';

            /* comment #1 */
            function f(int $a): string
            {
                /** comment #2 */
                return sprintf('0x%02x', $a);
            }
        }

        $b = f(77);
        echo "Value: {$b}";
        ?></div>
        PHP;

    /**
     * @var class-string<PhpToken|\PhpToken>
     */
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
            'If $input changed, replace $expected with: ' . $actualCode
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
                    'T_COMMENT',
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
                    'T_DOC_COMMENT',
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
            'If $input changed, replace $expected with: ' . $actualCode
        );
    }

    /**
     * @return array<array{bool[]|class-string<Throwable>,array<PhpToken|\PhpToken>,int|string|array<int|string>}>
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
            'If $input changed, replace $expected with: ' . $actualCode
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
            fn(string $code, $token) => sprintf(
                "%s    new static::\$Token(%s, %s, %d, %d),\n",
                $code,
                $token->id < 128 ? $token->id : '\\' . $token->getTokenName(),
                Get::code($token->text),
                $token->line,
                $token->pos
            ),
            "[\n"
        ) . ']';

        $this->assertEquals(
            $expected,
            $actual,
            'If $input changed, replace $expected with: ' . $actualCode
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
                    new static::$Token(\T_COMMENT, '/* comment #1 */', 6, 55),
                    new static::$Token(\T_WHITESPACE, "\n    ", 6, 71),
                    new static::$Token(\T_FUNCTION, 'function', 7, 76),
                    new static::$Token(\T_WHITESPACE, ' ', 7, 84),
                    new static::$Token(\T_STRING, 'f', 7, 85),
                    new static::$Token(40, '(', 7, 86),
                    new static::$Token(\T_STRING, 'int', 7, 87),
                    new static::$Token(\T_WHITESPACE, ' ', 7, 90),
                    new static::$Token(\T_VARIABLE, '$a', 7, 91),
                    new static::$Token(41, ')', 7, 93),
                    new static::$Token(58, ':', 7, 94),
                    new static::$Token(\T_WHITESPACE, ' ', 7, 95),
                    new static::$Token(\T_STRING, 'string', 7, 96),
                    new static::$Token(\T_WHITESPACE, "\n    ", 7, 102),
                    new static::$Token(123, '{', 8, 107),
                    new static::$Token(\T_WHITESPACE, "\n        ", 8, 108),
                    new static::$Token(\T_DOC_COMMENT, '/** comment #2 */', 9, 117),
                    new static::$Token(\T_WHITESPACE, "\n        ", 9, 134),
                    new static::$Token(\T_RETURN, 'return', 10, 143),
                    new static::$Token(\T_WHITESPACE, ' ', 10, 149),
                    new static::$Token(\T_STRING, 'sprintf', 10, 150),
                    new static::$Token(40, '(', 10, 157),
                    new static::$Token(\T_CONSTANT_ENCAPSED_STRING, "'0x%02x'", 10, 158),
                    new static::$Token(44, ',', 10, 166),
                    new static::$Token(\T_WHITESPACE, ' ', 10, 167),
                    new static::$Token(\T_VARIABLE, '$a', 10, 168),
                    new static::$Token(41, ')', 10, 170),
                    new static::$Token(59, ';', 10, 171),
                    new static::$Token(\T_WHITESPACE, "\n    ", 10, 172),
                    new static::$Token(125, '}', 11, 177),
                    new static::$Token(\T_WHITESPACE, "\n", 11, 178),
                    new static::$Token(125, '}', 12, 179),
                    new static::$Token(\T_WHITESPACE, "\n\n", 12, 180),
                    new static::$Token(\T_VARIABLE, '$b', 14, 182),
                    new static::$Token(\T_WHITESPACE, ' ', 14, 184),
                    new static::$Token(61, '=', 14, 185),
                    new static::$Token(\T_WHITESPACE, ' ', 14, 186),
                    new static::$Token(\T_STRING, 'f', 14, 187),
                    new static::$Token(40, '(', 14, 188),
                    new static::$Token(\T_LNUMBER, '77', 14, 189),
                    new static::$Token(41, ')', 14, 191),
                    new static::$Token(59, ';', 14, 192),
                    new static::$Token(\T_WHITESPACE, "\n", 14, 193),
                    new static::$Token(\T_ECHO, 'echo', 15, 194),
                    new static::$Token(\T_WHITESPACE, ' ', 15, 198),
                    new static::$Token(34, '"', 15, 199),
                    new static::$Token(\T_ENCAPSED_AND_WHITESPACE, 'Value: ', 15, 200),
                    new static::$Token(\T_CURLY_OPEN, '{', 15, 207),
                    new static::$Token(\T_VARIABLE, '$b', 15, 208),
                    new static::$Token(125, '}', 15, 210),
                    new static::$Token(34, '"', 15, 211),
                    new static::$Token(59, ';', 15, 212),
                    new static::$Token(\T_WHITESPACE, "\n", 15, 213),
                    new static::$Token(\T_CLOSE_TAG, '?>', 16, 214),
                    new static::$Token(\T_INLINE_HTML, '</div>', 16, 216),
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
                    new static::$Token(\T_COMMENT, '/* comment #1 */', 6, 55),
                    new static::$Token(\T_WHITESPACE, "\n    ", 6, 71),
                    new static::$Token(\T_FUNCTION, 'function', 7, 76),
                    new static::$Token(\T_WHITESPACE, ' ', 7, 84),
                    new static::$Token(\T_STRING, 'f', 7, 85),
                    new static::$Token(40, '(', 7, 86),
                    new static::$Token(\T_STRING, 'int', 7, 87),
                    new static::$Token(\T_WHITESPACE, ' ', 7, 90),
                    new static::$Token(\T_VARIABLE, '$a', 7, 91),
                    new static::$Token(41, ')', 7, 93),
                    new static::$Token(58, ':', 7, 94),
                    new static::$Token(\T_WHITESPACE, ' ', 7, 95),
                    new static::$Token(\T_STRING, 'string', 7, 96),
                    new static::$Token(\T_WHITESPACE, "\n    ", 7, 102),
                    new static::$Token(123, '{', 8, 107),
                    new static::$Token(\T_WHITESPACE, "\n        ", 8, 108),
                    new static::$Token(\T_DOC_COMMENT, '/** comment #2 */', 9, 117),
                    new static::$Token(\T_WHITESPACE, "\n        ", 9, 134),
                    new static::$Token(\T_RETURN, 'return', 10, 143),
                    new static::$Token(\T_WHITESPACE, ' ', 10, 149),
                    new static::$Token(\T_STRING, 'sprintf', 10, 150),
                    new static::$Token(40, '(', 10, 157),
                    new static::$Token(\T_CONSTANT_ENCAPSED_STRING, "'0x%02x'", 10, 158),
                    new static::$Token(44, ',', 10, 166),
                    new static::$Token(\T_WHITESPACE, ' ', 10, 167),
                    new static::$Token(\T_VARIABLE, '$a', 10, 168),
                    new static::$Token(41, ')', 10, 170),
                    new static::$Token(59, ';', 10, 171),
                    new static::$Token(\T_WHITESPACE, "\n    ", 10, 172),
                    new static::$Token(125, '}', 11, 177),
                    new static::$Token(\T_WHITESPACE, "\n", 11, 178),
                    new static::$Token(125, '}', 12, 179),
                    new static::$Token(\T_WHITESPACE, "\n\n", 12, 180),
                    new static::$Token(\T_VARIABLE, '$b', 14, 182),
                    new static::$Token(\T_WHITESPACE, ' ', 14, 184),
                    new static::$Token(61, '=', 14, 185),
                    new static::$Token(\T_WHITESPACE, ' ', 14, 186),
                    new static::$Token(\T_STRING, 'f', 14, 187),
                    new static::$Token(40, '(', 14, 188),
                    new static::$Token(\T_LNUMBER, '77', 14, 189),
                    new static::$Token(41, ')', 14, 191),
                    new static::$Token(59, ';', 14, 192),
                    new static::$Token(\T_WHITESPACE, "\n", 14, 193),
                    new static::$Token(\T_ECHO, 'echo', 15, 194),
                    new static::$Token(\T_WHITESPACE, ' ', 15, 198),
                    new static::$Token(34, '"', 15, 199),
                    new static::$Token(\T_ENCAPSED_AND_WHITESPACE, 'Value: ', 15, 200),
                    new static::$Token(\T_CURLY_OPEN, '{', 15, 207),
                    new static::$Token(\T_VARIABLE, '$b', 15, 208),
                    new static::$Token(125, '}', 15, 210),
                    new static::$Token(34, '"', 15, 211),
                    new static::$Token(59, ';', 15, 212),
                    new static::$Token(\T_WHITESPACE, "\n", 15, 213),
                    new static::$Token(\T_CLOSE_TAG, '?>', 16, 214),
                    new static::$Token(\T_INLINE_HTML, '</div>', 16, 216),
                ],
                self::CODE,
            ],
        ];
    }
}
