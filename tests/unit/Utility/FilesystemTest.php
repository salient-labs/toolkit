<?php declare(strict_types=1);

namespace Lkrms\Tests\Utility;

use Lkrms\Facade\File;

final class FilesystemTest extends \Lkrms\Tests\TestCase
{
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
