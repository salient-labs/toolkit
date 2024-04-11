<?php declare(strict_types=1);

namespace Salient\Tests\Core\Utility;

use org\bovigo\vfs\vfsStream;
use Salient\Core\Exception\FilesystemErrorException;
use Salient\Core\Exception\UnreadDataException;
use Salient\Core\Utility\File;
use Salient\Core\Utility\Str;
use Salient\Core\Utility\Sys;
use Salient\Tests\TestCase;
use InvalidArgumentException;
use Stringable;

/**
 * @covers \Salient\Core\Utility\File
 */
final class FileTest extends TestCase
{
    /**
     * @dataProvider sanitiseDirProvider
     */
    public function testSanitiseDir(string $expected, string $directory): void
    {
        $this->assertSame($expected, File::sanitiseDir($directory));
    }

    /**
     * @return array<array{string,string}>
     */
    public static function sanitiseDirProvider(): array
    {
        return [
            ['.', ''],
            ['dir', 'dir'],
            ['dir', 'dir/'],
            ['dir', 'dir///'],
            ['dir/subdir', 'dir/subdir'],
            ['dir/subdir', 'dir/subdir/'],
            ['/dir', '/dir'],
            ['/dir', '/dir/'],
            ['/dir', '/dir///'],
            ['C:', 'C:'],
            ['C:', 'C:/'],
            ['C:', 'C:' . \DIRECTORY_SEPARATOR],
            [\DIRECTORY_SEPARATOR, '/'],
            [\DIRECTORY_SEPARATOR, '/' . \DIRECTORY_SEPARATOR],
            [\DIRECTORY_SEPARATOR, \DIRECTORY_SEPARATOR . \DIRECTORY_SEPARATOR . '/'],
        ];
    }

    public function testGetCwd(): void
    {
        // chdir() resolves symbolic links, so this is all we can reliably test
        // without launching a separate process
        $dir = self::getFixturesPath(__CLASS__);
        File::chdir($dir);
        $this->assertSame(getcwd(), File::getcwd());
    }

    public function testGetCwdInSymlinkedDirectory(): void
    {
        $dir = File::realpath(self::getFixturesPath(__CLASS__));
        $command = sprintf(
            '%s && %s',
            Sys::escapeCommand(['cd', "$dir/dir_symlink"]),
            Sys::escapeCommand([...self::PHP_COMMAND, "$dir/pwd.php"]),
        );
        $handle = File::openPipe($command, 'rb');
        $output = File::getContents($handle);
        $status = File::closePipe($handle);
        $this->assertSame(0, $status);
        $this->assertSame($dir . \DIRECTORY_SEPARATOR . 'dir_symlink' . \PHP_EOL, $output);
    }

    /**
     * @requires OSFAMILY Darwin
     */
    public function testGetCwdOnDarwin(): void
    {
        $dir = File::createTempDir();
        try {
            File::createDir("$dir/not_searchable/dir");
            File::chdir("$dir/not_searchable/dir");
            File::chmod("$dir/not_searchable", 0600);
            $this->expectException(FilesystemErrorException::class);
            $this->expectExceptionMessage('Error getting current working directory');
            File::getcwd();
        } finally {
            File::pruneDir($dir, true, true);
        }
    }

    public function testFileOperations(): void
    {
        vfsStream::setup('root');
        $file = vfsStream::url('root/file');
        $this->assertFileDoesNotExist($file);
        $stream = File::open($file, 'w+');
        $this->assertIsResource($stream);
        $this->assertFileExists($file);
        $this->assertSame(0, File::size($file));
        $this->assertSame(0, File::tell($stream, $file));
        File::write($stream, $data = str_repeat("\0", $length = 1024), null, $file);
        $this->assertSame($length, File::tell($stream, $file));
        File::rewind($stream, $file);
        $this->assertSame(0, File::tell($stream, $file));
        clearstatcache();
        $this->assertSame($data, File::read($stream, $length * 2, $file));
        $this->assertSame($length, File::size($file));
        $this->assertSame($length, File::tell($stream, $file));
        File::truncate($stream, 0, $file);
        clearstatcache();
        $this->assertSame(0, File::size($file));
        $this->assertSame(0, File::tell($stream, $file));
        File::close($stream);
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

    public function testCreatable(): void
    {
        $dir = self::getFixturesPath(__CLASS__);
        $this->assertTrue(File::isCreatable("$dir/exists"));
        $this->assertTrue(File::isCreatable("$dir/dir/does_not_exist"));
        $this->assertFalse(File::isCreatable("$dir/dir/file/does_not_exist"));
        $this->assertTrue(File::isCreatable("$dir/not_a_dir/does_not_exist"));

        $dir = File::createTempDir();
        try {
            File::createDir("$dir/unwritable", 0500);
            $this->assertFalse(File::isCreatable("$dir/unwritable"));
            $this->assertFalse(File::isCreatable("$dir/unwritable/does_not_exist"));
            File::create("$dir/writable/file");
            File::create("$dir/writable/read_only", 0400);
            File::create("$dir/writable/no_permissions", 0);
            File::chmod("$dir/writable", 0500);
            $this->assertFalse(File::isCreatable("$dir/writable"));
            $this->assertTrue(File::isCreatable("$dir/writable/file"));
            $this->assertFalse(File::isCreatable("$dir/writable/read_only"));
            $this->assertFalse(File::isCreatable("$dir/writable/no_permissions"));
            $this->assertFalse(File::isCreatable("$dir/writable/does_not_exist"));
        } finally {
            File::pruneDir($dir, true, true);
        }
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
        $dir = self::getFixturesPath(__CLASS__);
        $this->assertSame(realpath("$dir/exists"), File::realpath("$dir/exists"));
        $this->expectException(FilesystemErrorException::class);
        $this->expectExceptionMessage("Error resolving path: $dir/does_not_exist");
        File::realpath("$dir/does_not_exist");
    }

    /**
     * @dataProvider resolveProvider
     */
    public function testResolve(string $expected, string $path, bool $withEmptySegments = false): void
    {
        $this->assertSame($expected, File::resolvePath($path, $withEmptySegments));
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

    public function testExisting(): void
    {
        $dir = self::getFixturesPath(__CLASS__);
        $this->assertSame("$dir/exists", File::closestExisting("$dir/exists"));
        $this->assertSame("$dir/dir", File::closestExisting("$dir/dir/does_not_exist"));
        $this->assertNull(File::closestExisting("$dir/dir/file/does_not_exist"));
        $this->assertSame("$dir", File::closestExisting("$dir/not_a_dir/does_not_exist"));
    }

    /**
     * @dataProvider readCsvProvider
     *
     * @param array<mixed[]> $expected
     * @param Stringable|string|resource $resource
     */
    public function testReadCsv($expected, $resource): void
    {
        $this->assertSame($expected, File::getCsv($resource));
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
        $dir = self::getFixturesPath(__CLASS__);

        return [
            [
                implode(\DIRECTORY_SEPARATOR, ['dir', 'file']),
                "$dir/dir/file",
                $dir,
            ],
            [
                null,
                $dir,
                "$dir/dir/file",
            ],
            [
                'fallback',
                $dir,
                "$dir/dir/file",
                'fallback',
            ],
            [
                -1,
                "$dir/dir/does_not_exist",
                $dir,
            ],
            [
                -1,
                "$dir/dir/file",
                "$dir/does_not_exist",
            ],
            [
                implode(\DIRECTORY_SEPARATOR, ['tests', 'fixtures', 'Toolkit', 'Core', 'Utility', 'File', 'dir', 'file']),
                "$dir/dir/file",
                self::getPackagePath(),
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
        $dir = self::getFixturesPath(__CLASS__);
        $this->assertTrue(File::same("$dir/dir/file", "$dir/dir/file"));
        $this->assertTrue(File::same("$dir/dir/file", "$dir/file_symlink"));
        $this->assertFalse(File::same("$dir/dir/file", "$dir/exists"));
        $this->assertFalse(File::same("$dir/dir/file", "$dir/broken_symlink"));
    }

    public function testReadAll(): void
    {
        // 1 MiB
        $data = str_repeat('0123456789abcdef', 2 ** 16);
        $stream = Str::toStream($data . str_repeat('0123456789abcdef', 2));
        $this->assertSame('', File::readAll($stream, 0));
        $this->assertSame($data, File::readAll($stream, 16 * 2 ** 16));
        $this->assertSame('0123456789abcdef', File::readAll($stream, 16));
        $this->expectException(UnreadDataException::class);
        $this->expectExceptionMessage('Error reading from stream: expected 8 more bytes from php://temp');
        File::readAll($stream, 24);
    }

    public function testWriteAll(): void
    {
        // 1 MiB
        $data = str_repeat('0123456789abcdef', 2 ** 16);
        $stream = File::open('php://temp', 'r+');
        File::writeAll($stream, $data);
        File::writeAll($stream, $data, 16);
        File::writeAll($stream, '');
        File::rewind($stream);
        $this->assertSame($data . '0123456789abcdef', File::getContents($stream));
    }

    /**
     * @dataProvider assertResourceIsStreamProvider
     *
     * @param mixed $value
     */
    public function testAssertResourceIsStream(?string $expected, $value, ?string $valueType = null): void
    {
        if ($expected === null) {
            $this->expectException(InvalidArgumentException::class);
            if ($valueType !== null) {
                if (is_resource($value)) {
                    $this->expectExceptionMessage("Invalid resource type: $valueType");
                } else {
                    $this->expectExceptionMessage("Argument #1 (\$resource) must be of type Stringable|string|resource, $valueType given");
                }
            }
        }
        // @phpstan-ignore-next-line
        $this->assertSame($expected, File::getContents($value));
    }

    /**
     * @return array<array{string|null,mixed,2?:string|null}>
     */
    public static function assertResourceIsStreamProvider(): array
    {
        $dir = self::getFixturesPath(__CLASS__);
        $file = "$dir/dir/file";
        $stream1 = File::open($file, 'r');
        $stream2 = File::open($file, 'r');
        File::close($stream2);

        return [
            [null, null, 'null'],
            ['', $file],
            ['', $stream1],
            [null, $stream2, 'resource (closed)'],
        ];
    }
}
