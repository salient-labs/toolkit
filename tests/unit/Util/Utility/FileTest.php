<?php declare(strict_types=1);

namespace Lkrms\Tests\Utility;

use Lkrms\Exception\FilesystemErrorException;
use Lkrms\Support\Indentation;
use Lkrms\Tests\TestCase;
use Lkrms\Utility\File;
use Lkrms\Utility\Package;
use Lkrms\Utility\Str;
use Stringable;

final class FileTest extends TestCase
{
    public function testCwd(): void
    {
        $this->assertTrue(File::same(File::cwd(), getcwd()));
    }

    /**
     * @dataProvider getEolProvider
     */
    public function testGetEol(?string $expected, string $content): void
    {
        $stream = Str::toStream($content);
        $this->assertSame($expected, File::getEol($stream));
    }

    /**
     * @return array<array{string|null,string}>
     */
    public static function getEolProvider(): array
    {
        return [
            [null, ''],
            [null, 'x'],
            ["\r\n", "\r\n"],
            ["\r\n", "x\r\n"],
            ["\r\n", "x\r\nx"],
            ["\n", "\n"],
            ["\n", "x\n"],
            ["\n", "x\nx"],
            ["\r", "\r"],
            ["\r", "x\r"],
            ["\r", "x\rx"],
            ["\n", "x\rx\n"],
            ["\r\n", "x\rx\r\n"],
        ];
    }

    /**
     * Derived from VS Code's textModel tests
     *
     * @dataProvider guessIndentationProvider
     *
     * @param int[]|int|null $expectedTabSize
     * @param string[] $lines
     *
     * @link https://github.com/microsoft/vscode/blob/860d67064a9c1ef8ce0c8de35a78bea01033f76c/src/vs/editor/test/common/model/textModel.test.ts
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
        $guess = File::guessIndentation($stream, $default);
        File::close($stream);
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

    /**
     * @dataProvider isAbsoluteProvider
     */
    public function testIsAbsolute(bool $expected, string $path): void
    {
        $this->assertSame($expected, File::isAbsolute($path));
    }

    /**
     * @return array<array{bool,string}>
     */
    public static function isAbsoluteProvider(): array
    {
        return [
            [false, ''],
            [false, '.'],
            [true, '/usr/local/Cellar/composer/2.6.5/bin/composer'],
            [true, 'C:\php\composer\2.6.5\bin\composer'],
            [false, 'C:bin/composer'],
            [true, '\\\\.\c$\php\composer\2.6.5\bin\composer'],
            [false, '\php\composer\2.6.5\bin\composer'],
            [false, 'bin/composer'],
            [true, 'c:/php/composer/2.6.5/bin/composer'],
            [false, 'composer'],
            [true, 'file:///usr/local/Cellar/composer/2.6.5/bin/composer'],
            [true, 'file:/usr/local/Cellar/composer/2.6.5/bin/composer'],
            [true, 'phar:///usr/local/Cellar/composer/2.6.5/bin/composer/bin/composer'],
        ];
    }

    /**
     * @dataProvider isPharUriProvider
     */
    public function testIsPharUri(bool $expected, string $path): void
    {
        $this->assertSame($expected, File::isPharUri($path));
    }

    /**
     * @return array<array{bool,string}>
     */
    public static function isPharUriProvider(): array
    {
        return [
            [true, 'phar:///usr/local/Cellar/composer/2.6.5/bin/composer/bin/composer'],
            [true, 'PHAR:///usr/local/Cellar/composer/2.6.5/bin/composer/bin/composer'],
            [false, '/usr/local/Cellar/composer/2.6.5/bin/composer'],
        ];
    }

    public function testRealpath(): void
    {
        $path = $this->getFixturesPath(__CLASS__);
        $this->assertSame(realpath("$path/exists"), File::realpath("$path/exists"));
        $this->expectException(FilesystemErrorException::class);
        $this->expectExceptionMessage("File not found: $path/does_not_exist");
        File::realpath("$path/does_not_exist");
    }

    /**
     * @dataProvider resolveProvider
     */
    public function testResolve(string $expected, string $path, bool $withEmptySegments = false): void
    {
        $this->assertSame($expected, File::resolve($path, $withEmptySegments));
    }

    /**
     * @return array<array{string,string,2?:bool}>
     */
    public static function resolveProvider(): array
    {
        return [
            [
                '',
                './././',
            ],
            [
                '/',
                '/././.',
            ],
            [
                '',
                '../../../',
            ],
            [
                '/',
                '/../../..',
            ],
            [
                'dir/subdir/file',
                './dir/subdir/file',
            ],
            [
                'dir/subdir/file',
                '../dir/subdir/file',
            ],
            [
                '/dir/subdir/file',
                '/./dir/subdir/file',
            ],
            [
                '/dir/subdir/file',
                '/../dir/subdir/file',
            ],
            [
                '/dir/subdir2/',
                '/dir/subdir/files/../../subdir2/.',
            ],
            [
                '/dir/subdir2/file',
                '/dir/subdir/files/../../subdir2/./file',
            ],
            [
                'C:/dir/subdir2/file',
                'C:\dir\subdir\files\..\..\subdir2\.\file',
            ],
            [
                '/dir/',
                '/dir/subdir//../',
            ],
            [
                '/dir/',
                '/dir/subdir//..',
            ],
            [
                '/dir/',
                '/dir/subdir///../',
            ],
            [
                '/dir/',
                '/dir/subdir///..',
            ],
            [
                '//dir//subdir//',
                '//dir//subdir//files//..',
            ],
            [
                '//dir//',
                '//dir//subdir//files//../..',
            ],
            [
                '//',
                '//dir//subdir//files//../../..',
            ],
            [
                '/',
                '//dir//subdir//files//../../../..',
            ],
            [
                '/',
                '//dir//subdir//files//../../../../..',
            ],
            [
                '/dir/subdir/',
                '/dir/subdir//../',
                true,
            ],
            [
                '/dir/subdir/',
                '/dir/subdir//..',
                true,
            ],
            [
                '/dir/subdir//',
                '/dir/subdir///../',
                true,
            ],
            [
                '/dir/subdir//',
                '/dir/subdir///..',
                true,
            ],
            [
                '/dir/',
                '/dir/subdir///../../../',
                true,
            ],
            [
                '/',
                '/dir/subdir///../../../../',
                true,
            ],
            [
                '/',
                '/dir/subdir///../../../../../',
                true,
            ],
            [
                '/dir/',
                '/dir//subdir//../../../',
                true,
            ],
            [
                '/dir/',
                '/dir///subdir/../../../',
                true,
            ],
            [
                '//',
                '///dir/subdir/../../../',
                true,
            ],
            [
                '/',
                '///dir/subdir/../../../../',
                true,
            ],
        ];
    }

    /**
     * @dataProvider readCsvProvider
     *
     * @param array<mixed[]> $expected
     * @param Stringable|string|resource $resource
     */
    public function testReadCsv($expected, $resource): void
    {
        $this->assertSame($expected, File::readCsv($resource));
    }

    /**
     * @return array<array{array<mixed[]>,Stringable|string|resource}>
     */
    public static function readCsvProvider(): array
    {
        $dir = self::getFixturesPath(__CLASS__) . '/csv';

        return [
            [
                [
                    [
                        'id',
                        'name',
                        'email',
                        'notes',
                    ],
                    [
                        '71',
                        'King, Terry',
                        '',
                        'Likes "everybody wants to rule the world"',
                    ],
                    [
                        '38',
                        'Amir',
                        'amir@domain.test',
                        'Website: https://domain.test/~amir',
                    ],
                    [
                        '32',
                        'Greta',
                        'greta@domain.test',
                        'Has a \backslash \"and escaped quotes\"',
                    ],
                ],
                $dir . '/utf8.csv',
            ],
        ];
    }

    /**
     * @dataProvider relativeToParentProvider
     *
     * @param string|int|null $expected
     */
    public function testRelativeToParent(
        $expected,
        string $filename,
        string $parentDir,
        ?string $fallback = null
    ): void {
        if ($expected === -1) {
            $this->expectException(FilesystemErrorException::class);
        }
        $this->assertSame($expected, File::relativeToParent($filename, $parentDir, $fallback));
    }

    /**
     * @return array<array{string|int|null,string,string,3?:string|null}>
     */
    public static function relativeToParentProvider(): array
    {
        $path = self::getFixturesPath(__CLASS__);

        return [
            [
                implode(\DIRECTORY_SEPARATOR, ['dir', 'file']),
                "$path/dir/file",
                $path,
            ],
            [
                null,
                $path,
                "$path/dir/file",
            ],
            [
                'fallback',
                $path,
                "$path/dir/file",
                'fallback',
            ],
            [
                -1,
                "$path/dir/does_not_exist",
                $path,
            ],
            [
                -1,
                "$path/dir/file",
                "$path/does_not_exist",
            ],
            [
                implode(\DIRECTORY_SEPARATOR, ['tests', 'fixtures', 'Util', 'Utility', 'File', 'dir', 'file']),
                "$path/dir/file",
                Package::path(),
            ],
        ];
    }

    public function testGetStablePath(): void
    {
        $path = File::getStablePath();
        $dir = dirname($path);
        $this->assertTrue(File::isAbsolute($path));
        $this->assertDirectoryExists($dir);
        $this->assertIsWritable($dir);
    }

    public function testSame(): void
    {
        $path = $this->getFixturesPath(__CLASS__);
        $this->assertTrue(File::same("$path/dir/file", "$path/dir/file"));
        $this->assertTrue(File::same("$path/dir/file", "$path/file_symlink"));
        $this->assertFalse(File::same("$path/dir/file", "$path/exists"));
        $this->assertFalse(File::same("$path/dir/file", "$path/broken_symlink"));
    }
}
