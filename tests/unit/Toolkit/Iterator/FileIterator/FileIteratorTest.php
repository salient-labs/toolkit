<?php declare(strict_types=1);

namespace Salient\Tests\Iterator\FileIterator;

use Salient\Iterator\FileIterator;
use Salient\Tests\TestCase;
use LogicException;
use SplFileInfo;

/**
 * @covers \Salient\Iterator\FileIterator
 */
final class FileIteratorTest extends TestCase
{
    /**
     * @dataProvider iteratorProvider
     *
     * @param string[] $expected
     */
    public function testIterator(
        array $expected,
        FileIterator $iterator,
        string $replace,
        bool $sort = true
    ): void {
        $actual = [];
        foreach ($iterator as $file) {
            $actual[] = str_replace($replace, '', (string) $file);
        }
        if ($sort) {
            sort($expected);
            sort($actual);
        }
        $this->assertEquals($expected, $actual);
    }

    /**
     * @return array<array{string[],FileIterator,string,3?:bool}>
     */
    public static function iteratorProvider(): array
    {
        $dir = __DIR__ . '/data';
        $files = [
            '/.hidden',
            '/dir1/.hidden',
            '/dir1/dir2/.hidden',
            '/dir1/dir2/file2',
            '/dir1/dir2/file6.ext',
            '/dir1/file3',
            '/dir1/file4.ext',
            '/dir2/.hidden',
            '/dir2/dir3/.hidden',
            '/dir2/dir3/file7',
            '/dir2/dir3/file8.ext',
            '/dir2/file5',
            '/dir2/file6.ext',
            '/file1',
            '/file2.ext',
        ];

        return [
            [
                $files,
                (new FileIterator())
                    ->files()
                    ->in($dir),
                $dir,
            ],
            [
                [
                    '/.hidden',
                    '/dir1',
                    '/dir1/.hidden',
                    '/dir1/dir2',
                    '/dir1/dir2/.hidden',
                    '/dir1/dir2/file2',
                    '/dir1/dir2/file6.ext',
                    '/dir1/file3',
                    '/dir1/file4.ext',
                    '/dir2',
                    '/dir2/.hidden',
                    '/dir2/dir3',
                    '/dir2/dir3/.hidden',
                    '/dir2/dir3/file7',
                    '/dir2/dir3/file8.ext',
                    '/dir2/file5',
                    '/dir2/file6.ext',
                    '/file1',
                    '/file2.ext',
                ],
                (new FileIterator())
                    ->in($dir),
                $dir,
            ],
            [
                [
                    '/dir1',
                    '/dir1/dir2',
                    '/dir2',
                    '/dir2/dir3',
                ],
                (new FileIterator())
                    ->directories()
                    ->in($dir),
                $dir,
            ],
            [
                $files,
                (new FileIterator())
                    ->directories()
                    ->files()
                    ->in($dir),
                $dir,
            ],
            [
                [
                    '/dir1/dir2/.hidden',
                    '/dir1/dir2/file2',
                    '/dir1/dir2/file6.ext',
                    '/dir2/.hidden',
                    '/dir2/dir3/.hidden',
                    '/dir2/dir3/file7',
                    '/dir2/dir3/file8.ext',
                    '/dir2/file5',
                    '/dir2/file6.ext',
                ],
                (new FileIterator())
                    ->files()
                    ->in($dir)
                    ->include('/\/dir2\//'),
                $dir,
            ],
            [
                [
                    '/.hidden',
                    '/dir1/.hidden',
                    '/dir1/dir2/.hidden',
                    '/dir2/.hidden',
                    '/dir2/dir3/.hidden',
                ],
                (new FileIterator())
                    ->in($dir)
                    ->include('/\/\.hidden$/'),
                $dir,
            ],
            [
                [
                    '/dir2',
                    '/dir2/dir3',
                ],
                (new FileIterator())
                    ->in($dir)
                    ->directories()
                    ->directoriesFirst()
                    ->include('/^\/dir2\//')
                    ->relative(),
                $dir,
                false,
            ],
            [
                [
                    '/dir2/dir3',
                    '/dir2',
                ],
                (new FileIterator())
                    ->in($dir)
                    ->directories()
                    ->directoriesLast()
                    ->include('/^\/dir2\//')
                    ->relative(),
                $dir,
                false,
            ],
            [
                [
                    '/dir1/dir2',
                    '/dir2',
                ],
                (new FileIterator())
                    ->in($dir)
                    ->directories()
                    ->include('/\/dir2$/'),
                $dir,
            ],
            [
                [
                    '/.hidden',
                    '/file1',
                    '/file2.ext',
                ],
                (new FileIterator())
                    ->files()
                    ->in($dir)
                    ->doNotRecurse(),
                $dir,
            ],
            [
                [
                    '/.hidden',
                    '/dir1',
                    '/dir2',
                    '/file1',
                    '/file2.ext',
                ],
                (new FileIterator())
                    ->in($dir)
                    ->doNotRecurse(),
                $dir,
            ],
            [
                [
                    '/dir1',
                    '/dir2',
                    '/file1',
                    '/file2.ext',
                ],
                (new FileIterator())
                    ->in($dir)
                    ->doNotRecurse()
                    ->exclude('/\/\.hidden$/'),
                $dir,
            ],
            [
                [
                    '/.hidden',
                    '/dir1',
                    '/dir2',
                ],
                (new FileIterator())
                    ->in($dir)
                    ->doNotRecurse()
                    ->exclude('/^file[0-9]+/')
                    ->include('/\/[^\/]*\.[^\/]*$/')
                    ->include(fn(SplFileInfo $f) => $f->isDir())
                    ->relative(),
                $dir,
            ],
            [
                [
                    '/.hidden',
                    '/dir1/.hidden',
                    '/dir1/dir2',
                    '/dir1/dir2/.hidden',
                    '/dir1/dir2/file6.ext',
                    '/dir1/file4.ext',
                    '/dir2/.hidden',
                    '/dir2/dir3',
                    '/dir2/dir3/.hidden',
                    '/dir2/dir3/file8.ext',
                    '/dir2/file6.ext',
                ],
                (new FileIterator())
                    ->in($dir)
                    ->exclude('/^file[0-9]+/')
                    ->include('/\/[^\/]*\.[^\/]*$/')
                    ->include(
                        fn(SplFileInfo $file, string $path, int $depth) =>
                            $file->isDir() && $depth === 1
                    )
                    ->relative(),
                $dir,
            ],
        ];
    }

    /**
     * @dataProvider getFirstWithProvider
     *
     * @param string|bool|null $expected
     * @param mixed $value
     */
    public function testGetFirstWith(
        $expected,
        string $dir,
        string $key,
        $value,
        bool $strict = false
    ): void {
        if ($expected === false) {
            $this->expectException(LogicException::class);
        }

        $file = (new FileIterator())
            ->in($dir)
            ->getFirstWith($key, $value, $strict);

        if ($file instanceof SplFileInfo) {
            $file = (string) $file;
        }

        $this->assertSame($expected, $file);
    }

    /**
     * @return array<array{string|bool|null,string,string,mixed,4?:bool}>
     */
    public static function getFirstWithProvider(): array
    {
        $dir = __DIR__ . '/data';
        return [
            [
                "{$dir}/dir2/file5",
                $dir,
                'inode',
                fileinode("{$dir}/dir2/file5"),
                true,
            ],
            [
                null,
                $dir,
                'isLink',
                true,
                true,
            ],
            [
                false,
                $dir,
                'isDirectory',
                true,
            ],
            [
                false,
                $dir,
                'openFile',
                true,
            ],
        ];
    }
}
