<?php declare(strict_types=1);

namespace Lkrms\Tests\Store;

use Lkrms\Exception\AssertionFailedException;
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
        $this->Cache->set('key1', 'value1');

        $this->reopenCache();

        $this->assertTrue($this->Cache->has(__METHOD__));
        $this->assertSame($arr, $this->Cache->get(__METHOD__));
        $this->assertEqualsCanonicalizing([__METHOD__, 'key1'], $this->Cache->getAllKeys());

        $this->Cache->delete(__METHOD__);
        $this->assertSame(['key1'], $this->Cache->getAllKeys());
        $this->assertFalse($this->Cache->has(__METHOD__));
        $this->assertFalse($this->Cache->get(__METHOD__));
    }

    public function testWithExpiration(): void
    {
        $arr = ['foo' => 'bar'];
        $this->Cache->set(__METHOD__, $arr, new DateTimeImmutable('24 hours ago'));
        $this->Cache->set('key0', 'value0', new DateTimeImmutable('10 seconds ago'));
        $this->Cache->set('key1', 'value1', 60);
        $this->Cache->set('key2', 'value2', time() + 60);
        $now = time();

        $this->reopenCache();

        // "rewind" by 30 seconds
        $current = $this->Cache->asOfNow($now - 30);
        $this->assertFalse($this->Cache->has(__METHOD__));
        $this->assertFalse($this->Cache->has('key0'));
        $this->assertFalse($current->has(__METHOD__));
        $this->assertTrue($current->has('key0'));
        $this->assertTrue($this->Cache->has(__METHOD__, 30));
        $this->assertTrue($this->Cache->has(__METHOD__, 0));
        $this->assertSame('value0', $current->get('key0'));
        $this->assertSame($arr, $this->Cache->get(__METHOD__, 30));
        $this->assertSame($arr, $this->Cache->get(__METHOD__, 0));

        $this->assertEqualsCanonicalizing(['key1', 'key2'], $this->Cache->getAllKeys());
        $this->assertEqualsCanonicalizing(['key0', 'key1', 'key2'], $current->getAllKeys());
        $this->assertEqualsCanonicalizing([__METHOD__, 'key0', 'key1', 'key2'], $this->Cache->getAllKeys(30));
        $this->assertEqualsCanonicalizing([__METHOD__, 'key0', 'key1', 'key2'], $this->Cache->getAllKeys(0));
        $this->assertSame(2, $this->Cache->getItemCount());
        $this->assertSame(3, $current->getItemCount());
        $this->assertSame(4, $this->Cache->getItemCount(30));
        $this->assertSame(4, $this->Cache->getItemCount(0));

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

    public function testGetInstanceOf(): void
    {
        $this->assertFalse($this->Cache->getInstanceOf(__METHOD__, stdClass::class));

        $objIn = new stdClass();
        $objIn->Foo = 'bar';
        $this->Cache->set(__METHOD__, $objIn);

        $objOut = $this->Cache->getInstanceOf(__METHOD__, stdClass::class);
        $this->assertInstanceOf(stdClass::class, $objOut);
        $this->assertEquals($objIn, $objOut);
        $this->assertNotSame($objIn, $objOut);

        $arr = ['foo' => 'bar'];
        $this->Cache->set(__METHOD__, $arr);
        $this->expectException(AssertionFailedException::class);
        $this->Cache->getInstanceOf(__METHOD__, stdClass::class);
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
        $this->assertEqualsCanonicalizing(['key1', 'key2'], $this->Cache->getAllKeys(0));
        $this->Cache->deleteAll();
        $this->assertFalse($this->Cache->get('key1'));
        $this->assertFalse($this->Cache->get('key2'));
        $this->assertSame([], $this->Cache->getAllKeys(0));
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
