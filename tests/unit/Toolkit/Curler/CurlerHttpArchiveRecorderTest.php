<?php declare(strict_types=1);

namespace Salient\Tests\Curler;

use org\bovigo\vfs\vfsStream;
use org\bovigo\vfs\vfsStreamDirectory;
use Salient\Curler\CurlerHttpArchiveRecorder;
use Salient\Tests\HttpTestCase;
use Salient\Utility\File;

final class CurlerHttpArchiveRecorderTest extends HttpTestCase
{
    private vfsStreamDirectory $Root;

    public function testConstructor(): void
    {
        $file = $this->Root->url() . '/archive.har';
        new CurlerHttpArchiveRecorder($file, 'app', 'v1.0.0');
        $this->assertSame(
            '{"log":{"version":"1.2","creator":{"name":"app","version":"v1.0.0"},"pages":[],"entries":[]}}',
            File::getContents($file),
        );
    }

    protected function setUp(): void
    {
        $this->Root = vfsStream::setup();
    }
}
