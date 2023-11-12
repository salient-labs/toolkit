<?php declare(strict_types=1);

namespace Lkrms\Tests\Utility;

use Lkrms\Exception\FilesystemErrorException;
use Lkrms\Utility\File;

final class FileTest extends \Lkrms\Tests\TestCase
{
    public function testRealpath(): void
    {
        $path = $this->getFixturesPath(__CLASS__);
        $exists = File::realpath("$path/exists");
        $this->assertIsString($exists);
        $this->assertSame(realpath("$path/exists"), $exists);
        $this->assertSame(false, File::realpath("$path/does_not_exist"));
        $this->assertSame('php://fd/6', File::realpath('/dev/fd/6'));
        $this->assertSame('php://fd/6', File::realpath('/proc/self/fd/6'));
        $this->assertSame('php://fd/6', File::realpath('/proc/93698/fd/6'));
    }

    /**
     * @dataProvider relativeToParentProvider
     */
    public function testRelativeToParent(?string $expected, string $filename, ?string $parentDir = null): void
    {
        if ($expected === null) {
            $this->expectException(FilesystemErrorException::class);
            File::relativeToParent($filename, $parentDir);
            return;
        }

        $this->assertSame($expected, File::relativeToParent($filename, $parentDir));
    }

    /**
     * @return array<array{string|null,string,2?:string|null}>
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
                "$path/dir/does_not_exist",
                $path,
            ],
            [
                null,
                "$path/dir/file",
                "$path/does_not_exist",
            ],
            [
                implode(\DIRECTORY_SEPARATOR, ['tests', 'fixtures', 'Utility', 'File', 'dir', 'file']),
                "$path/dir/file",
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
