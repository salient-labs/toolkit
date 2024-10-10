<?php declare(strict_types=1);

namespace Salient\Tests\Sli\Internal;

use Salient\Sli\Internal\NavigableToken;
use Salient\Tests\TestCase;
use Salient\Utility\Get;
use Salient\Utility\Str;

/**
 * @covers \Salient\Sli\Internal\NavigableToken
 */
final class NavigableTokenTest extends TestCase
{
    private const CODE = <<<'PHP'
<?php
class Foo
{
    /**
     * Get the answer
     */
    public function bar(?string $question = null): int
    {
        // Ignore the question
        return 42;
    }

    //
}
PHP;

    /**
     * @dataProvider tokenizeProvider
     *
     * @param array<array{int,int,string,array{int|null,int|null},array{int|null,int|null},int|null,array{int|null,int|null}}> $expected
     */
    public function testTokenize(
        array $expected,
        string $code,
        int $flags = 0,
        bool $discardWhitespace = false
    ): void {
        $tokens = NavigableToken::tokenize(Str::eolFromNative($code), $flags, $discardWhitespace);
        [$actual, $actualCode] = self::serializeTokens($tokens);
        $this->assertSame(
            $expected,
            $actual,
            'If $code changed, replace $expected with: ' . $actualCode,
        );
    }

    /**
     * @return array<array{array<array{int,int,string,array{int|null,int|null},array{int|null,int|null},int|null,array{int|null,int|null}}>,string,2?:int,3?:bool}>
     */
    public static function tokenizeProvider(): array
    {
        return [
            [
                [
                    [0, \T_OPEN_TAG, "<?php\n", [null, 1], [null, 1], null, [null, null]],
                    [1, \T_CLASS, 'class', [0, 2], [null, 3], null, [null, null]],
                    [2, \T_WHITESPACE, ' ', [1, 3], [1, 3], null, [null, null]],
                    [3, \T_STRING, 'Foo', [2, 4], [1, 5], null, [null, null]],
                    [4, \T_WHITESPACE, "\n", [3, 5], [3, 5], null, [null, null]],
                    [5, 123, '{', [4, 6], [3, 9], null, [null, null]],
                    [6, \T_WHITESPACE, "\n    ", [5, 7], [5, 9], null, [null, null]],
                    [7, \T_DOC_COMMENT, "/**\n     * Get the answer\n     */", [6, 8], [5, 9], null, [null, null]],
                    [8, \T_WHITESPACE, "\n    ", [7, 9], [5, 9], null, [null, null]],
                    [9, \T_PUBLIC, 'public', [8, 10], [5, 11], null, [null, null]],
                    [10, \T_WHITESPACE, ' ', [9, 11], [9, 11], null, [null, null]],
                    [11, \T_FUNCTION, 'function', [10, 12], [9, 13], null, [null, null]],
                    [12, \T_WHITESPACE, ' ', [11, 13], [11, 13], null, [null, null]],
                    [13, \T_STRING, 'bar', [12, 14], [11, 14], null, [null, null]],
                    [14, 40, '(', [13, 15], [13, 15], null, [null, null]],
                    [15, 63, '?', [14, 16], [14, 16], null, [null, null]],
                    [16, \T_STRING, 'string', [15, 17], [15, 18], null, [null, null]],
                    [17, \T_WHITESPACE, ' ', [16, 18], [16, 18], null, [null, null]],
                    [18, \T_VARIABLE, '$question', [17, 19], [16, 20], null, [null, null]],
                    [19, \T_WHITESPACE, ' ', [18, 20], [18, 20], null, [null, null]],
                    [20, 61, '=', [19, 21], [18, 22], null, [null, null]],
                    [21, \T_WHITESPACE, ' ', [20, 22], [20, 22], null, [null, null]],
                    [22, \T_STRING, 'null', [21, 23], [20, 23], null, [null, null]],
                    [23, 41, ')', [22, 24], [22, 24], null, [null, null]],
                    [24, 58, ':', [23, 25], [23, 26], null, [null, null]],
                    [25, \T_WHITESPACE, ' ', [24, 26], [24, 26], null, [null, null]],
                    [26, \T_STRING, 'int', [25, 27], [24, 28], null, [null, null]],
                    [27, \T_WHITESPACE, "\n    ", [26, 28], [26, 28], null, [null, null]],
                    [28, 123, '{', [27, 29], [26, 32], null, [null, null]],
                    [29, \T_WHITESPACE, "\n        ", [28, 30], [28, 32], null, [null, null]],
                    [30, \T_COMMENT, '// Ignore the question', [29, 31], [28, 32], null, [null, null]],
                    [31, \T_WHITESPACE, "\n        ", [30, 32], [28, 32], null, [null, null]],
                    [32, \T_RETURN, 'return', [31, 33], [28, 34], null, [null, null]],
                    [33, \T_WHITESPACE, ' ', [32, 34], [32, 34], null, [null, null]],
                    [34, \T_LNUMBER, '42', [33, 35], [32, 35], null, [null, null]],
                    [35, 59, ';', [34, 36], [34, 37], null, [null, null]],
                    [36, \T_WHITESPACE, "\n    ", [35, 37], [35, 37], null, [null, null]],
                    [37, 125, '}', [36, 38], [35, 41], null, [null, null]],
                    [38, \T_WHITESPACE, "\n\n    ", [37, 39], [37, 41], null, [null, null]],
                    [39, \T_COMMENT, '//', [38, 40], [37, 41], null, [null, null]],
                    [40, \T_WHITESPACE, "\n", [39, 41], [37, 41], null, [null, null]],
                    [41, 125, '}', [40, null], [37, null], null, [null, null]],
                ],
                self::CODE,
            ],
            [
                [
                    [0, \T_OPEN_TAG, "<?php\n", [null, 1], [null, 1], null, [null, null]],
                    [1, \T_CLASS, 'class', [0, 2], [null, 3], null, [null, null]],
                    [2, \T_WHITESPACE, ' ', [1, 3], [1, 3], null, [null, null]],
                    [3, \T_STRING, 'Foo', [2, 4], [1, 5], null, [null, null]],
                    [4, \T_WHITESPACE, "\n", [3, 5], [3, 5], null, [null, null]],
                    [5, 123, '{', [4, 6], [3, 9], null, [null, 41]],
                    [6, \T_WHITESPACE, "\n    ", [5, 7], [5, 9], 5, [null, null]],
                    [7, \T_DOC_COMMENT, "/**\n     * Get the answer\n     */", [6, 8], [5, 9], 5, [null, null]],
                    [8, \T_WHITESPACE, "\n    ", [7, 9], [5, 9], 5, [null, null]],
                    [9, \T_PUBLIC, 'public', [8, 10], [5, 11], 5, [null, null]],
                    [10, \T_WHITESPACE, ' ', [9, 11], [9, 11], 5, [null, null]],
                    [11, \T_FUNCTION, 'function', [10, 12], [9, 13], 5, [null, null]],
                    [12, \T_WHITESPACE, ' ', [11, 13], [11, 13], 5, [null, null]],
                    [13, \T_STRING, 'bar', [12, 14], [11, 14], 5, [null, null]],
                    [14, 40, '(', [13, 15], [13, 15], 5, [null, 23]],
                    [15, 63, '?', [14, 16], [14, 16], 14, [null, null]],
                    [16, \T_STRING, 'string', [15, 17], [15, 18], 14, [null, null]],
                    [17, \T_WHITESPACE, ' ', [16, 18], [16, 18], 14, [null, null]],
                    [18, \T_VARIABLE, '$question', [17, 19], [16, 20], 14, [null, null]],
                    [19, \T_WHITESPACE, ' ', [18, 20], [18, 20], 14, [null, null]],
                    [20, 61, '=', [19, 21], [18, 22], 14, [null, null]],
                    [21, \T_WHITESPACE, ' ', [20, 22], [20, 22], 14, [null, null]],
                    [22, \T_STRING, 'null', [21, 23], [20, 23], 14, [null, null]],
                    [23, 41, ')', [22, 24], [22, 24], 5, [14, null]],
                    [24, 58, ':', [23, 25], [23, 26], 5, [null, null]],
                    [25, \T_WHITESPACE, ' ', [24, 26], [24, 26], 5, [null, null]],
                    [26, \T_STRING, 'int', [25, 27], [24, 28], 5, [null, null]],
                    [27, \T_WHITESPACE, "\n    ", [26, 28], [26, 28], 5, [null, null]],
                    [28, 123, '{', [27, 29], [26, 32], 5, [null, 37]],
                    [29, \T_WHITESPACE, "\n        ", [28, 30], [28, 32], 28, [null, null]],
                    [30, \T_COMMENT, '// Ignore the question', [29, 31], [28, 32], 28, [null, null]],
                    [31, \T_WHITESPACE, "\n        ", [30, 32], [28, 32], 28, [null, null]],
                    [32, \T_RETURN, 'return', [31, 33], [28, 34], 28, [null, null]],
                    [33, \T_WHITESPACE, ' ', [32, 34], [32, 34], 28, [null, null]],
                    [34, \T_LNUMBER, '42', [33, 35], [32, 35], 28, [null, null]],
                    [35, 59, ';', [34, 36], [34, 37], 28, [null, null]],
                    [36, \T_WHITESPACE, "\n    ", [35, 37], [35, 37], 28, [null, null]],
                    [37, 125, '}', [36, 38], [35, 41], 5, [28, null]],
                    [38, \T_WHITESPACE, "\n\n    ", [37, 39], [37, 41], 5, [null, null]],
                    [39, \T_COMMENT, '//', [38, 40], [37, 41], 5, [null, null]],
                    [40, \T_WHITESPACE, "\n", [39, 41], [37, 41], 5, [null, null]],
                    [41, 125, '}', [40, null], [37, null], null, [5, null]],
                ],
                self::CODE,
                \TOKEN_PARSE,
            ],
            [
                [
                    [0, \T_OPEN_TAG, "<?php\n", [null, 1], [null, 1], null, [null, null]],
                    [1, \T_CLASS, 'class', [0, 2], [null, 2], null, [null, null]],
                    [2, \T_STRING, 'Foo', [1, 3], [1, 3], null, [null, null]],
                    [3, 123, '{', [2, 4], [2, 5], null, [null, null]],
                    [4, \T_DOC_COMMENT, "/**\n     * Get the answer\n     */", [3, 5], [3, 5], null, [null, null]],
                    [5, \T_PUBLIC, 'public', [4, 6], [3, 6], null, [null, null]],
                    [6, \T_FUNCTION, 'function', [5, 7], [5, 7], null, [null, null]],
                    [7, \T_STRING, 'bar', [6, 8], [6, 8], null, [null, null]],
                    [8, 40, '(', [7, 9], [7, 9], null, [null, null]],
                    [9, 63, '?', [8, 10], [8, 10], null, [null, null]],
                    [10, \T_STRING, 'string', [9, 11], [9, 11], null, [null, null]],
                    [11, \T_VARIABLE, '$question', [10, 12], [10, 12], null, [null, null]],
                    [12, 61, '=', [11, 13], [11, 13], null, [null, null]],
                    [13, \T_STRING, 'null', [12, 14], [12, 14], null, [null, null]],
                    [14, 41, ')', [13, 15], [13, 15], null, [null, null]],
                    [15, 58, ':', [14, 16], [14, 16], null, [null, null]],
                    [16, \T_STRING, 'int', [15, 17], [15, 17], null, [null, null]],
                    [17, 123, '{', [16, 18], [16, 19], null, [null, null]],
                    [18, \T_COMMENT, '// Ignore the question', [17, 19], [17, 19], null, [null, null]],
                    [19, \T_RETURN, 'return', [18, 20], [17, 20], null, [null, null]],
                    [20, \T_LNUMBER, '42', [19, 21], [19, 21], null, [null, null]],
                    [21, 59, ';', [20, 22], [20, 22], null, [null, null]],
                    [22, 125, '}', [21, 23], [21, 24], null, [null, null]],
                    [23, \T_COMMENT, '//', [22, 24], [22, 24], null, [null, null]],
                    [24, 125, '}', [23, null], [22, null], null, [null, null]],
                ],
                self::CODE,
                0,
                true,
            ],
            [
                [
                    [0, \T_OPEN_TAG, "<?php\n", [null, 1], [null, 1], null, [null, null]],
                    [1, \T_CLASS, 'class', [0, 2], [null, 2], null, [null, null]],
                    [2, \T_STRING, 'Foo', [1, 3], [1, 3], null, [null, null]],
                    [3, 123, '{', [2, 4], [2, 5], null, [null, 24]],
                    [4, \T_DOC_COMMENT, "/**\n     * Get the answer\n     */", [3, 5], [3, 5], 3, [null, null]],
                    [5, \T_PUBLIC, 'public', [4, 6], [3, 6], 3, [null, null]],
                    [6, \T_FUNCTION, 'function', [5, 7], [5, 7], 3, [null, null]],
                    [7, \T_STRING, 'bar', [6, 8], [6, 8], 3, [null, null]],
                    [8, 40, '(', [7, 9], [7, 9], 3, [null, 14]],
                    [9, 63, '?', [8, 10], [8, 10], 8, [null, null]],
                    [10, \T_STRING, 'string', [9, 11], [9, 11], 8, [null, null]],
                    [11, \T_VARIABLE, '$question', [10, 12], [10, 12], 8, [null, null]],
                    [12, 61, '=', [11, 13], [11, 13], 8, [null, null]],
                    [13, \T_STRING, 'null', [12, 14], [12, 14], 8, [null, null]],
                    [14, 41, ')', [13, 15], [13, 15], 3, [8, null]],
                    [15, 58, ':', [14, 16], [14, 16], 3, [null, null]],
                    [16, \T_STRING, 'int', [15, 17], [15, 17], 3, [null, null]],
                    [17, 123, '{', [16, 18], [16, 19], 3, [null, 22]],
                    [18, \T_COMMENT, '// Ignore the question', [17, 19], [17, 19], 17, [null, null]],
                    [19, \T_RETURN, 'return', [18, 20], [17, 20], 17, [null, null]],
                    [20, \T_LNUMBER, '42', [19, 21], [19, 21], 17, [null, null]],
                    [21, 59, ';', [20, 22], [20, 22], 17, [null, null]],
                    [22, 125, '}', [21, 23], [21, 24], 3, [17, null]],
                    [23, \T_COMMENT, '//', [22, 24], [22, 24], 3, [null, null]],
                    [24, 125, '}', [23, null], [22, null], null, [3, null]],
                ],
                self::CODE,
                \TOKEN_PARSE,
                true,
            ],
        ];
    }

    /**
     * @dataProvider getInnerTokensProvider
     *
     * @param array<array{int,int,string,array{int|null,int|null},array{int|null,int|null},int|null,array{int|null,int|null}}> $expected
     */
    public function testGetInnerTokens(
        array $expected,
        string $code,
        int $tokenIndex,
        int $flags = \TOKEN_PARSE
    ): void {
        $tokens = NavigableToken::tokenize(Str::eolFromNative($code), $flags, true)[$tokenIndex]->getInnerTokens();
        [$actual, $actualCode] = self::serializeTokens($tokens);
        $this->assertSame(
            $expected,
            $actual,
            'If $code changed, replace $expected with: ' . $actualCode,
        );
    }

    /**
     * @return array<array{array<array{int,int,string,array{int|null,int|null},array{int|null,int|null},int|null,array{int|null,int|null}}>,string,int,3?:int}>
     */
    public static function getInnerTokensProvider(): array
    {
        return [
            [
                [
                    [0, \T_DOC_COMMENT, "/**\n     * Get the answer\n     */", [null, 1], [null, 1], null, [null, null]],
                    [1, \T_PUBLIC, 'public', [0, 2], [null, 2], null, [null, null]],
                    [2, \T_FUNCTION, 'function', [1, 3], [1, 3], null, [null, null]],
                    [3, \T_STRING, 'bar', [2, 4], [2, 4], null, [null, null]],
                    [4, 40, '(', [3, 5], [3, 5], null, [null, 10]],
                    [5, 63, '?', [4, 6], [4, 6], 4, [null, null]],
                    [6, \T_STRING, 'string', [5, 7], [5, 7], 4, [null, null]],
                    [7, \T_VARIABLE, '$question', [6, 8], [6, 8], 4, [null, null]],
                    [8, 61, '=', [7, 9], [7, 9], 4, [null, null]],
                    [9, \T_STRING, 'null', [8, 10], [8, 10], 4, [null, null]],
                    [10, 41, ')', [9, 11], [9, 11], null, [4, null]],
                    [11, 58, ':', [10, 12], [10, 12], null, [null, null]],
                    [12, \T_STRING, 'int', [11, 13], [11, 13], null, [null, null]],
                    [13, 123, '{', [12, 14], [12, 15], null, [null, 18]],
                    [14, \T_COMMENT, '// Ignore the question', [13, 15], [13, 15], 13, [null, null]],
                    [15, \T_RETURN, 'return', [14, 16], [13, 16], 13, [null, null]],
                    [16, \T_LNUMBER, '42', [15, 17], [15, 17], 13, [null, null]],
                    [17, 59, ';', [16, 18], [16, 18], 13, [null, null]],
                    [18, 125, '}', [17, 19], [17, null], null, [13, null]],
                    [19, \T_COMMENT, '//', [18, null], [18, null], null, [null, null]],
                ],
                self::CODE,
                3,
            ],
            [
                [],
                self::CODE,
                3,
                0,
            ],
            [
                [
                    [0, 63, '?', [null, 1], [null, 1], null, [null, null]],
                    [1, \T_STRING, 'string', [0, 2], [0, 2], null, [null, null]],
                    [2, \T_VARIABLE, '$question', [1, 3], [1, 3], null, [null, null]],
                    [3, 61, '=', [2, 4], [2, 4], null, [null, null]],
                    [4, \T_STRING, 'null', [3, null], [3, null], null, [null, null]],
                ],
                self::CODE,
                8,
            ],
            [
                [
                    [0, \T_COMMENT, '// Ignore the question', [null, 1], [null, 1], null, [null, null]],
                    [1, \T_RETURN, 'return', [0, 2], [null, 2], null, [null, null]],
                    [2, \T_LNUMBER, '42', [1, 3], [1, 3], null, [null, null]],
                    [3, 59, ';', [2, null], [2, null], null, [null, null]],
                ],
                self::CODE,
                22,
            ],
        ];
    }

    public function testIsDeclarationOf(): void
    {
        $tokens = NavigableToken::tokenize(self::CODE, 0, true);
        $this->assertFalse($tokens[0]->isDeclarationOf(\T_CLASS));
        $this->assertTrue($tokens[1]->isDeclarationOf(\T_CLASS));
        $this->assertTrue($tokens[2]->isDeclarationOf(\T_CLASS));
        $this->assertFalse($tokens[2]->isDeclarationOf(\T_FUNCTION));
        $this->assertFalse($tokens[3]->isDeclarationOf(\T_CLASS));
        $this->assertFalse($tokens[4]->isDeclarationOf(\T_CLASS));
        $this->assertFalse($tokens[5]->isDeclarationOf(\T_FUNCTION));
        $this->assertTrue($tokens[6]->isDeclarationOf(\T_FUNCTION));
        $this->assertFalse($tokens[6]->isDeclarationOf(\T_CLASS));
        $this->assertTrue($tokens[7]->isDeclarationOf(\T_FUNCTION));
        $this->assertFalse($tokens[8]->isDeclarationOf(\T_FUNCTION));
    }

    /**
     * @param NavigableToken[] $tokens
     * @return array{array<array{int,int,string,array{int|null,int|null},array{int|null,int|null},int|null,array{int|null,int|null}}>,string}
     */
    private static function serializeTokens(array $tokens): array
    {
        foreach ($tokens as $token) {
            $actual[$token->Index] = $actualToken = [
                $token->Index,
                $token->id,
                $token->text,
                [$token->Prev->Index ?? null, $token->Next->Index ?? null],
                [$token->PrevCode->Index ?? null, $token->NextCode->Index ?? null],
                $token->Parent->Index ?? null,
                [$token->OpenedBy->Index ?? null, $token->ClosedBy->Index ?? null],
            ];
            $tokenName = $token->getTokenName();
            if ($tokenName !== null && strlen($tokenName) > 1) {
                $tokenName = '\\' . $tokenName;
                $actualToken[1] = $tokenName;
                $constants[$tokenName] = $tokenName;
            }
            $actualCode[$token->Index] = $actualToken;
        }
        $actualCode = Get::code(
            $actualCode ?? [],
            ', ',
            ' => ',
            null,
            '    ',
            [],
            $constants ?? [],
        );
        return [$actual ?? [], $actualCode];
    }
}
