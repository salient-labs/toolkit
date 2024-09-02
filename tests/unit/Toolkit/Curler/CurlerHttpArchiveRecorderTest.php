<?php declare(strict_types=1);

namespace Salient\Tests\Curler;

use org\bovigo\vfs\vfsStream;
use org\bovigo\vfs\vfsStreamDirectory;
use Salient\Core\Facade\Cache;
use Salient\Curler\CurlerHttpArchiveRecorder;
use Salient\Tests\HttpTestCase;
use Salient\Utility\File;
use Salient\Utility\Json;

/**
 * @covers \Salient\Curler\CurlerHttpArchiveRecorder
 */
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

    public function testRecording(): void
    {
        $file = $this->Root->url() . '/archive.har';
        $recorder = new CurlerHttpArchiveRecorder($file, 'app', 'v1.0.0');
        $recorder->start();
        $this->startHttpServer();
        $curler = $this->getCurler()->withUserAgent('app/1.0.0')->withResponseCache();
        $curler->get(['foo' => 'bar']);
        $curler->get(['foo' => 'bar']);
        $recorder->close();

        $har = Json::parseObjectAsArray(File::getContents($file));
        $this->assertIsArray($har);
        $this->assertArrayHasKey('log', $har);
        $this->assertIsArray($log = $har['log']);
        $this->assertSame(['version', 'creator', 'pages', 'entries'], array_keys($log));
        $this->assertIsArray($entries = $log['entries']);
        $this->assertCount(2, $entries);
        $this->assertIsArray($entry1 = $entries[0] ?? null);
        $this->assertIsArray($entry2 = $entries[1] ?? null);
        $this->assertArrayHasKey('serverIPAddress', $entry1);
        $this->assertArrayNotHasKey('serverIPAddress', $entry2);
        $this->assertIsArray($timings1 = $entry1['timings'] ?? null);
        $this->assertIsArray($timings2 = $entry2['timings'] ?? null);
        $this->assertIsInt($timings1['dns'] ?? null);
        $this->assertGreaterThanOrEqual(0, $timings1['dns']);
        $this->assertSame(-1, $timings2['dns'] ?? null);
        $this->assertIsArray($entry1['response'] ?? null);
        $this->assertSame($entry1['response'], $entry2['response'] ?? null);
    }

    protected function setUp(): void
    {
        $this->Root = vfsStream::setup();
    }

    protected function tearDown(): void
    {
        if (Cache::isLoaded()) {
            Cache::unload();
        }

        parent::tearDown();
    }
}
