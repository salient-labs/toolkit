<?php declare(strict_types=1);

namespace Salient\Tests\Utility\Support;

use Salient\Tests\TestCase;
use Salient\Utility\Support\Indentation;
use Salient\Utility\Str;

/**
 * @covers \Salient\Utility\Support\Indentation
 */
final class IndentationTest extends TestCase
{
    /**
     * Derived from VS Code's textModel tests
     *
     * @link https://github.com/microsoft/vscode/blob/860d67064a9c1ef8ce0c8de35a78bea01033f76c/src/vs/editor/test/common/model/textModel.test.ts
     *
     * @dataProvider guessIndentationProvider
     *
     * @param int[]|int|null $expectedTabSize
     * @param string[] $lines
     */
    public function testGuessIndentation(?bool $expectedInsertSpaces, $expectedTabSize, array $lines, ?Indentation $default = null): void
    {
        if ($expectedInsertSpaces === null) {
            // Cannot guess InsertSpaces
            if ($expectedTabSize === null) {
                // Cannot guess TabSize
                $this->assertGuessIndentationReturns(true, 13370, $lines, $default ?? new Indentation(true, 13370));
                $this->assertGuessIndentationReturns(false, 13371, $lines, $default ?? new Indentation(false, 13371));
            } elseif (is_int($expectedTabSize)) {
                // Can guess TabSize
                $this->assertGuessIndentationReturns(true, $expectedTabSize, $lines, $default ?? new Indentation(true, 13370));
                $this->assertGuessIndentationReturns(false, $expectedTabSize, $lines, $default ?? new Indentation(false, 13371));
            } else {
                // Can only guess TabSize when InsertSpaces is true
                $this->assertGuessIndentationReturns(true, $expectedTabSize[0], $lines, $default ?? new Indentation(true, 13370));
                $this->assertGuessIndentationReturns(false, 13371, $lines, $default ?? new Indentation(false, 13371));
            }
        } else {
            // Can guess InsertSpaces
            if ($expectedTabSize === null) {
                // Cannot guess TabSize
                $this->assertGuessIndentationReturns($expectedInsertSpaces, 13370, $lines, $default ?? new Indentation(true, 13370));
                $this->assertGuessIndentationReturns($expectedInsertSpaces, 13371, $lines, $default ?? new Indentation(false, 13371));
            } elseif (is_int($expectedTabSize)) {
                // Can guess TabSize
                $this->assertGuessIndentationReturns($expectedInsertSpaces, $expectedTabSize, $lines, $default ?? new Indentation(true, 13370));
                $this->assertGuessIndentationReturns($expectedInsertSpaces, $expectedTabSize, $lines, $default ?? new Indentation(false, 13371));
            } elseif ($expectedInsertSpaces === true) {
                // Can only guess TabSize when InsertSpaces is true
                $this->assertGuessIndentationReturns($expectedInsertSpaces, $expectedTabSize[0], $lines, $default ?? new Indentation(true, 13370));
                $this->assertGuessIndentationReturns($expectedInsertSpaces, $expectedTabSize[0], $lines, $default ?? new Indentation(false, 13371));
            } else {
                $this->assertGuessIndentationReturns($expectedInsertSpaces, 13370, $lines, $default ?? new Indentation(true, 13370));
                $this->assertGuessIndentationReturns($expectedInsertSpaces, 13371, $lines, $default ?? new Indentation(false, 13371));
            }
        }
    }

    /**
     * @param string[] $lines
     */
    private function assertGuessIndentationReturns(bool $expectedInsertSpaces, int $expectedTabSize, array $lines, Indentation $default): void
    {
        $stream = Str::toStream(implode("\n", $lines));
        $guess = Indentation::from($stream, $default);
        $this->assertSame($expectedTabSize, $guess->TabSize);
        $this->assertSame($expectedInsertSpaces, $guess->InsertSpaces);
    }

    /**
     * @return array<string,array{bool|null,int[]|int|null,string[],3?:Indentation|null}>
     */
    public static function guessIndentationProvider(): array
    {
        return [
            'no clues' => [
                null,
                null,
                [
                    'x',
                    'x',
                    'x',
                    'x',
                    'x',
                    'x',
                    'x',
                ],
            ],
            'no spaces, 1xTAB' => [
                false,
                null,
                [
                    "\tx",
                    'x',
                    'x',
                    'x',
                    'x',
                    'x',
                    'x',
                ],
            ],
            '1x2' => [
                true,
                2,
                [
                    '  x',
                    'x',
                    'x',
                    'x',
                    'x',
                    'x',
                    'x',
                ],
            ],
            '7xTAB' => [
                false,
                null,
                [
                    "\tx",
                    "\tx",
                    "\tx",
                    "\tx",
                    "\tx",
                    "\tx",
                    "\tx",
                ],
            ],
            '4x2, 4xTAB' => [
                null,
                [2],
                [
                    "\tx",
                    '  x',
                    "\tx",
                    '  x',
                    "\tx",
                    '  x',
                    "\tx",
                    '  x',
                ],
            ],
            '4x1, 4xTAB' => [
                false,
                null,
                [
                    "\tx",
                    ' x',
                    "\tx",
                    ' x',
                    "\tx",
                    ' x',
                    "\tx",
                    ' x',
                ],
            ],
            '4x2, 5xTAB' => [
                false,
                null,
                [
                    "\tx",
                    "\tx",
                    '  x',
                    "\tx",
                    '  x',
                    "\tx",
                    '  x',
                    "\tx",
                    '  x',
                ],
            ],
            '1x2, 5xTAB' => [
                false,
                null,
                [
                    "\tx",
                    "\tx",
                    'x',
                    "\tx",
                    'x',
                    "\tx",
                    'x',
                    "\tx",
                    '  x',
                ],
            ],
            '1x4, 5xTAB' => [
                false,
                null,
                [
                    "\tx",
                    "\tx",
                    'x',
                    "\tx",
                    'x',
                    "\tx",
                    'x',
                    "\tx",
                    '    x',
                ],
            ],
            '1x2, 1x4, 5xTAB' => [
                false,
                null,
                [
                    "\tx",
                    "\tx",
                    'x',
                    "\tx",
                    'x',
                    "\tx",
                    '  x',
                    "\tx",
                    '    x',
                ],
            ],
            '7x1 - 1 space is never guessed as an indentation' => [
                null,
                null,
                [
                    'x',
                    ' x',
                    ' x',
                    ' x',
                    ' x',
                    ' x',
                    ' x',
                    ' x',
                ],
            ],
            '1x10, 6x1' => [
                true,
                null,
                [
                    'x',
                    '          x',
                    ' x',
                    ' x',
                    ' x',
                    ' x',
                    ' x',
                    ' x',
                ],
            ],
            "whitespace lines don't count" => [
                null,
                null,
                [
                    '',
                    '  ',
                    '    ',
                    '      ',
                    '        ',
                    '          ',
                    '            ',
                    '              ',
                ],
            ],
            '6x3, 3x4' => [
                true,
                3,
                [
                    'x',
                    '   x',
                    '   x',
                    '    x',
                    'x',
                    '   x',
                    '   x',
                    '    x',
                    'x',
                    '   x',
                    '   x',
                    '    x',
                ],
            ],
            '6x5, 3x4' => [
                true,
                5,
                [
                    'x',
                    '     x',
                    '     x',
                    '    x',
                    'x',
                    '     x',
                    '     x',
                    '    x',
                    'x',
                    '     x',
                    '     x',
                    '    x',
                ],
            ],
            '6x7, 1x5, 2x4' => [
                true,
                7,
                [
                    'x',
                    '       x',
                    '       x',
                    '     x',
                    'x',
                    '       x',
                    '       x',
                    '    x',
                    'x',
                    '       x',
                    '       x',
                    '    x',
                ],
            ],
            '8x2 1' => [
                true,
                2,
                [
                    'x',
                    '  x',
                    '  x',
                    '  x',
                    '  x',
                    'x',
                    '  x',
                    '  x',
                    '  x',
                    '  x',
                ],
            ],
            '8x2 2' => [
                true,
                2,
                [
                    'x',
                    '  x',
                    '  x',
                    'x',
                    '  x',
                    '  x',
                    'x',
                    '  x',
                    '  x',
                    'x',
                    '  x',
                    '  x',
                ],
            ],
            '4x2, 4x4 1' => [
                true,
                2,
                [
                    'x',
                    '  x',
                    '    x',
                    'x',
                    '  x',
                    '    x',
                    'x',
                    '  x',
                    '    x',
                    'x',
                    '  x',
                    '    x',
                ],
            ],
            '6x2, 3x4' => [
                true,
                2,
                [
                    'x',
                    '  x',
                    '  x',
                    '    x',
                    'x',
                    '  x',
                    '  x',
                    '    x',
                    'x',
                    '  x',
                    '  x',
                    '    x',
                ],
            ],
            '4x2, 4x4 2' => [
                true,
                2,
                [
                    'x',
                    '  x',
                    '  x',
                    '    x',
                    '    x',
                    'x',
                    '  x',
                    '  x',
                    '    x',
                    '    x',
                ],
            ],
            '2x2, 4x4' => [
                true,
                2,
                [
                    'x',
                    '  x',
                    '    x',
                    '    x',
                    'x',
                    '  x',
                    '    x',
                    '    x',
                ],
            ],
            '8x4' => [
                true,
                4,
                [
                    'x',
                    '    x',
                    '    x',
                    'x',
                    '    x',
                    '    x',
                    'x',
                    '    x',
                    '    x',
                    'x',
                    '    x',
                    '    x',
                ],
            ],
            '2x2, 4x4, 2x6' => [
                true,
                2,
                [
                    'x',
                    '  x',
                    '    x',
                    '    x',
                    '      x',
                    'x',
                    '  x',
                    '    x',
                    '    x',
                    '      x',
                ],
            ],
            '1x2, 2x4, 2x6, 1x8' => [
                true,
                2,
                [
                    'x',
                    '  x',
                    '    x',
                    '    x',
                    '      x',
                    '      x',
                    '        x',
                ],
            ],
            '6x4, 2x5, 2x8' => [
                true,
                4,
                [
                    'x',
                    '    x',
                    '    x',
                    '    x',
                    '     x',
                    '        x',
                    'x',
                    '    x',
                    '    x',
                    '    x',
                    '     x',
                    '        x',
                ],
            ],
            '3x4, 1x5, 2x8' => [
                true,
                4,
                [
                    'x',
                    '    x',
                    '    x',
                    '    x',
                    '     x',
                    '        x',
                    '        x',
                ],
            ],
            '6x4, 2x5, 4x8' => [
                true,
                4,
                [
                    'x',
                    'x',
                    '    x',
                    '    x',
                    '     x',
                    '        x',
                    '        x',
                    'x',
                    'x',
                    '    x',
                    '    x',
                    '     x',
                    '        x',
                    '        x',
                ],
            ],
            '5x1, 2x0, 1x3, 2x4' => [
                true,
                3,
                [
                    'x',
                    ' x',
                    ' x',
                    ' x',
                    ' x',
                    ' x',
                    'x',
                    '   x',
                    '    x',
                    '    x',
                ],
            ],
            'mixed whitespace 1' => [
                false,
                null,
                [
                    "\t x",
                    " \t x",
                    "\tx",
                ],
            ],
            'mixed whitespace 2' => [
                false,
                null,
                [
                    "\tx",
                    "\t    x",
                ],
            ],
            'issue #44991: Wrong indentation size auto-detection' => [
                true,
                4,
                [
                    'a = 10             # 0 space indent',
                    'b = 5              # 0 space indent',
                    'if a > 10:         # 0 space indent',
                    '    a += 1         # 4 space indent      delta 4 spaces',
                    '    if b > 5:      # 4 space indent',
                    '        b += 1     # 8 space indent      delta 4 spaces',
                    '        b += 1     # 8 space indent',
                    '        b += 1     # 8 space indent',
                    '# comment line 1   # 0 space indent      delta 8 spaces',
                    '# comment line 2   # 0 space indent',
                    '# comment line 3   # 0 space indent',
                    '        b += 1     # 8 space indent      delta 8 spaces',
                    '        b += 1     # 8 space indent',
                    '        b += 1     # 8 space indent',
                ],
            ],
            'issue #55818: Broken indentation detection' => [
                true,
                2,
                [
                    '',
                    '/* REQUIRE */',
                    '',
                    "const foo = require ( 'foo' ),",
                    "      bar = require ( 'bar' );",
                    '',
                    '/* MY FN */',
                    '',
                    'function myFn () {',
                    '',
                    '  const asd = 1,',
                    '        dsa = 2;',
                    '',
                    '  return bar ( foo ( asd ) );',
                    '',
                    '}',
                    '',
                    '/* EXPORT */',
                    '',
                    'module.exports = myFn;',
                    '',
                ],
            ],
            'issue #70832: Broken indentation detection' => [
                false,
                null,
                [
                    'x',
                    'x',
                    'x',
                    'x',
                    "\tx",
                    "\t\tx",
                    '    x',
                    "\t\tx",
                    "\tx",
                    "\t\tx",
                    "\tx",
                    "\tx",
                    "\tx",
                    "\tx",
                    'x',
                ],
            ],
            'issue #62143: Broken indentation detection 1' => [
                true,
                2,
                [
                    'x',
                    'x',
                    '  x',
                    '  x',
                ],
            ],
            'issue #62143: Broken indentation detection 2' => [
                true,
                2,
                [
                    'x',
                    '  - item2',
                    '  - item3',
                ],
            ],
            'issue #62143: Broken indentation detection 3' => [
                true,
                2,
                [
                    'x x',
                    '  x',
                    '  x',
                ],
                new Indentation(true, 2),
            ],
            'issue #62143: Broken indentation detection 4' => [
                true,
                2,
                [
                    'x x',
                    '  x',
                    '  x',
                    '    x',
                ],
                new Indentation(true, 2),
            ],
            'issue #62143: Broken indentation detection 5' => [
                true,
                2,
                [
                    '<!--test1.md -->',
                    '- item1',
                    '  - item2',
                    '    - item3',
                ],
                new Indentation(true, 2),
            ],
            'issue #84217: Broken indentation detection 1' => [
                true,
                4,
                [
                    'def main():',
                    "    print('hello')",
                ],
            ],
            'issue #84217: Broken indentation detection 2' => [
                true,
                4,
                [
                    'def main():',
                    "    with open('foo') as fp:",
                    '        print(fp.read())',
                ],
            ],
        ];
    }
}
