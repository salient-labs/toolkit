<?php declare(strict_types=1);

namespace Lkrms\Tests\Store;

use Lkrms\Store\CacheStore;
use Lkrms\Utility\File;
use DateTimeImmutable;
use stdClass;

final class CacheStoreTest extends \Lkrms\Tests\TestCase
{
    private ?string $Dir;

    private ?string $File;

    private ?CacheStore $Cache;

    public function testWithoutExpiration(): void
    {
        $objIn = new stdClass();
        $objIn->Foo = 'bar';
        $this->Cache->set(__METHOD__, $objIn);

        $this->reopenCache();

        $objOut = $this->Cache->get(__METHOD__);
        $this->assertSame('bar', $objOut->Foo);
        $this->assertEquals($objIn, $objOut);
        $this->assertNotSame($objIn, $objOut);
        $this->assertTrue($this->Cache->has(__METHOD__));
        $this->assertFalse($this->Cache->has('foo'));

        $arr = ['foo' => 'bar'];
        $this->Cache->set(__METHOD__, $arr);

        $this->reopenCache();

        $this->assertTrue($this->Cache->has(__METHOD__));
        $this->assertSame($arr, $this->Cache->get(__METHOD__));

        $this->Cache->delete(__METHOD__);
        $this->assertFalse($this->Cache->has(__METHOD__));
        $this->assertFalse($this->Cache->get(__METHOD__));
    }

    public function testWithExpiration(): void
    {
        $arr = ['foo' => 'bar'];
        $this->Cache->set(__METHOD__, $arr, new DateTimeImmutable('24 hours ago'));
        $this->Cache->set('key1', 'value1', 60);
        $this->Cache->set('key2', 'value2', time() + 60);
        $now = time();

        $this->reopenCache();

        $this->assertFalse($this->Cache->has(__METHOD__));
        $this->assertTrue($this->Cache->has(__METHOD__, 0));
        $this->assertSame($arr, $this->Cache->get(__METHOD__, 0));

        $this->Cache->flush();
        $this->assertFalse($this->Cache->has(__METHOD__, 0));
        $this->assertFalse($this->Cache->get(__METHOD__, 0));

        $this->assertSame('value1', $this->Cache->get('key1'));
        $this->assertSame('value2', $this->Cache->get('key2'));

        // "sleep" for 30 seconds
        $current = $this->Cache->asOfNow($now + 30);
        $this->assertSame('value1', $current->get('key1'));
        $this->assertSame('value2', $current->get('key2'));

        // "sleep" for a further 90 seconds
        $current = $this->Cache->asOfNow($now + 120);
        $this->assertSame(false, $current->get('key1'));
        $this->assertSame(false, $current->get('key2'));
    }

    public function testMaybeGet(): void
    {
        $counter = 0;
        $callback = function () use (&$counter) { return ++$counter; };
        for ($i = 0; $i < 4; $i++) {
            $this->Cache->maybeGet(__METHOD__, $callback, 60);
        }
        $this->assertSame(1, $this->Cache->get(__METHOD__));

        // "sleep" for 2 minutes
        $current = $this->Cache->asOfNow(time() + 120);
        $current->maybeGet(__METHOD__, $callback, 60);
        $this->assertSame(2, $this->Cache->get(__METHOD__));
    }

    public function testDeleteAll(): void
    {
        $this->Cache->set('key1', 'value1');
        $this->Cache->set('key2', 'value2');
        $this->assertSame('value1', $this->Cache->get('key1'));
        $this->assertSame('value2', $this->Cache->get('key2'));
        $this->Cache->deleteAll();
        $this->assertFalse($this->Cache->get('key1'));
        $this->assertFalse($this->Cache->get('key2'));
    }

    protected function setUp(): void
    {
        $this->Dir = File::createTempDir();
        $this->File = $this->Dir . '/cache.db';
        $this->Cache = new CacheStore($this->File);
    }

    protected function reopenCache(): void
    {
        $this->Cache->close();
        $this->Cache = new CacheStore($this->File);
    }

    protected function tearDown(): void
    {
        $this->Cache->close();
        File::pruneDir($this->Dir);
        rmdir($this->Dir);

        $this->Cache = null;
        $this->File = null;
        $this->Dir = null;
    }
}
