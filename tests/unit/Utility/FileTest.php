<?php declare(strict_types=1);

namespace Lkrms\Tests\Utility;

use Lkrms\Exception\FilesystemErrorException;
use Lkrms\Tests\TestCase;
use Lkrms\Utility\File;
use Lkrms\Utility\Package;

final class FileTest extends TestCase
{
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
                implode(\DIRECTORY_SEPARATOR, ['tests', 'fixtures', 'Utility', 'File', 'dir', 'file']),
                "$path/dir/file",
                Package::path(),
            ],
        ];
    }

    public function testGetStablePath(): void
    {
        $path = File::getStablePath();
        $dir = dirname($path);
        // $path should be absolute
        $this->assertMatchesRegularExpression('/^(\/|\\\\\\\\|[a-z]:\\\\)/i', $path);
        $this->assertDirectoryExists($dir);
        $this->assertIsWritable($dir);
    }
}
