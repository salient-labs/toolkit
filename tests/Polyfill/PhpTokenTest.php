<?php declare(strict_types=1);

namespace Lkrms\Tests\Polyfill;

use Lkrms\Facade\Convert;
use Lkrms\Polyfill\PhpToken;

final class PhpTokenTest extends \Lkrms\Tests\TestCase
{
    /**
     * @dataProvider tokenizeProvider
     *
     * @param PhpToken[] $expected
     */
    public function testTokenize(string $input, array $expected)
    {
        $actual = PhpToken::tokenize($input, TOKEN_PARSE);
        $actualCode = array_reduce(
            $actual,
            fn(string $code, PhpToken $token) => sprintf(
                "%s    new PhpToken(%s, %s, %d, %d),\n",
                $code,
                $token->id < 128 ? $token->id : $token->getTokenName(),
                Convert::valueToCode($token->text),
                $token->line,
                $token->pos
            ),
            "[\n"
        ) . ']';

        $this->assertEquals($expected, $actual, 'If $input changed, replace $expected with: ' . $actualCode);
    }

    /**
     * @return array<array{string,PhpToken[]}>
     */
    public static function tokenizeProvider(): array
    {
        return [
            'embedded script' => [
                <<<'PHP'
                <div><?php
                /* comment #1 */
                function f(int $a): string
                {
                    /** comment #2 */
                    return sprintf('0x%02x', $a);
                }

                $b = f(77);
                echo "Value: {$b}";
                ?></div>
                PHP,
                [
                    new PhpToken(T_INLINE_HTML, '<div>', 1, 0),
                    new PhpToken(T_OPEN_TAG, "<?php\n", 1, 5),
                    new PhpToken(T_COMMENT, '/* comment #1 */', 2, 11),
                    new PhpToken(T_WHITESPACE, "\n", 2, 27),
                    new PhpToken(T_FUNCTION, 'function', 3, 28),
                    new PhpToken(T_WHITESPACE, ' ', 3, 36),
                    new PhpToken(T_STRING, 'f', 3, 37),
                    new PhpToken(40, '(', 3, 38),
                    new PhpToken(T_STRING, 'int', 3, 39),
                    new PhpToken(T_WHITESPACE, ' ', 3, 42),
                    new PhpToken(T_VARIABLE, '$a', 3, 43),
                    new PhpToken(41, ')', 3, 45),
                    new PhpToken(58, ':', 3, 46),
                    new PhpToken(T_WHITESPACE, ' ', 3, 47),
                    new PhpToken(T_STRING, 'string', 3, 48),
                    new PhpToken(T_WHITESPACE, "\n", 3, 54),
                    new PhpToken(123, '{', 4, 55),
                    new PhpToken(T_WHITESPACE, "\n    ", 4, 56),
                    new PhpToken(T_DOC_COMMENT, '/** comment #2 */', 5, 61),
                    new PhpToken(T_WHITESPACE, "\n    ", 5, 78),
                    new PhpToken(T_RETURN, 'return', 6, 83),
                    new PhpToken(T_WHITESPACE, ' ', 6, 89),
                    new PhpToken(T_STRING, 'sprintf', 6, 90),
                    new PhpToken(40, '(', 6, 97),
                    new PhpToken(T_CONSTANT_ENCAPSED_STRING, "'0x%02x'", 6, 98),
                    new PhpToken(44, ',', 6, 106),
                    new PhpToken(T_WHITESPACE, ' ', 6, 107),
                    new PhpToken(T_VARIABLE, '$a', 6, 108),
                    new PhpToken(41, ')', 6, 110),
                    new PhpToken(59, ';', 6, 111),
                    new PhpToken(T_WHITESPACE, "\n", 6, 112),
                    new PhpToken(125, '}', 7, 113),
                    new PhpToken(T_WHITESPACE, "\n\n", 7, 114),
                    new PhpToken(T_VARIABLE, '$b', 9, 116),
                    new PhpToken(T_WHITESPACE, ' ', 9, 118),
                    new PhpToken(61, '=', 9, 119),
                    new PhpToken(T_WHITESPACE, ' ', 9, 120),
                    new PhpToken(T_STRING, 'f', 9, 121),
                    new PhpToken(40, '(', 9, 122),
                    new PhpToken(T_LNUMBER, '77', 9, 123),
                    new PhpToken(41, ')', 9, 125),
                    new PhpToken(59, ';', 9, 126),
                    new PhpToken(T_WHITESPACE, "\n", 9, 127),
                    new PhpToken(T_ECHO, 'echo', 10, 128),
                    new PhpToken(T_WHITESPACE, ' ', 10, 132),
                    new PhpToken(34, '"', 10, 133),
                    new PhpToken(T_ENCAPSED_AND_WHITESPACE, 'Value: ', 10, 134),
                    new PhpToken(T_CURLY_OPEN, '{', 10, 141),
                    new PhpToken(T_VARIABLE, '$b', 10, 142),
                    new PhpToken(125, '}', 10, 144),
                    new PhpToken(34, '"', 10, 145),
                    new PhpToken(59, ';', 10, 146),
                    new PhpToken(T_WHITESPACE, "\n", 10, 147),
                    new PhpToken(T_CLOSE_TAG, '?>', 11, 148),
                    new PhpToken(T_INLINE_HTML, '</div>', 11, 150),
                ],
            ],
        ];
    }
}
