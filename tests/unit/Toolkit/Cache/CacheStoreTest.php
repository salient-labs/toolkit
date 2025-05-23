<?php declare(strict_types=1);

namespace Salient\Tests\Cache;

use Salient\Cache\CacheStore;
use Salient\Contract\Cache\CacheCopyFailedException;
use Salient\Tests\TestCase;
use Salient\Utility\File;
use DateInterval;
use DateTimeImmutable;
use stdClass;

/**
 * @covers \Salient\Cache\CacheStore
 * @covers \Salient\Core\Store
 */
final class CacheStoreTest extends TestCase
{
    private string $Dir;
    private string $File;
    private CacheStore $Cache;

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
        $this->assertEqualsCanonicalizing([__METHOD__, 'key1'], $this->Cache->getItemKeys());

        $this->Cache->delete(__METHOD__);
        $this->assertSame(['key1'], $this->Cache->getItemKeys());
        $this->assertFalse($this->Cache->has(__METHOD__));
        $this->assertNull($this->Cache->get(__METHOD__));

        $this->Cache->set('key1', 'value1', 0);
        $this->assertSame([], $this->Cache->getItemKeys());
        $this->assertFalse($this->Cache->has('key1'));
        $this->assertNull($this->Cache->get('key1'));
    }

    public function testWithExpiration(): void
    {
        $this->Cache->set('key0', 'value0', new DateTimeImmutable('10 seconds ago'));
        $this->Cache->set('key1', 'value1', 60);
        $this->Cache->set('key2', 'value2', new DateInterval('PT1M'));
        $now = time();

        // "rewind" by 30 seconds
        $current = $this->Cache->asOfNow($now - 30);
        $this->assertFalse($this->Cache->has('key0'));
        $this->assertTrue($current->has('key0'));
        $this->assertSame('value0', $current->get('key0'));
        $this->assertEqualsCanonicalizing(['key1', 'key2'], $this->Cache->getItemKeys());
        $this->assertEqualsCanonicalizing(['key0', 'key1', 'key2'], $current->getItemKeys());
        $this->assertSame(2, $this->Cache->getItemCount());
        $this->assertSame(3, $current->getItemCount());
        $current = null;

        $this->Cache->clearExpired();
        $this->assertSame('value1', $this->Cache->get('key1'));
        $this->assertSame('value2', $this->Cache->get('key2'));

        // "sleep" for 30 seconds
        $current = $this->Cache->asOfNow($now + 30);
        $this->assertSame('value1', $current->get('key1'));
        $this->assertSame('value2', $current->get('key2'));
        $current->close();

        // "sleep" for a further 90 seconds
        $current = $this->Cache->asOfNow($now + 120);
        $this->assertNull($current->get('key1'));
        $this->assertNull($current->get('key2'));
    }

    public function testAsOfNow(): void
    {
        $locked = $this->Cache->asOfNow();
        $notLocked = new CacheStore($this->File);
        $this->assertFalse($locked->has('foo'));
        $this->assertFalse($notLocked->has('foo'));
        $locked->set('foo', 'bar');
        $this->assertTrue($locked->has('foo'));
        $this->assertFalse($notLocked->has('foo'));
        $locked = null;
        $this->assertTrue($notLocked->has('foo'));
        $locked = $this->Cache->asOfNow();
        $this->assertTrue($locked->has('foo'));
        $locked->clear();
        $this->assertFalse($locked->has('foo'));
        $this->assertTrue($notLocked->has('foo'));
        $locked->close();
        $this->assertFalse($notLocked->has('foo'));
    }

    public function testNestedAsOfNow(): void
    {
        $current = $this->Cache->asOfNow();
        $this->expectException(CacheCopyFailedException::class);
        $this->expectExceptionMessage('Calls to ' . CacheStore::class . '::asOfNow() cannot be nested');
        $current->asOfNow();
    }

    public function testMultipleAsOfNow(): void
    {
        $current = $this->Cache->asOfNow();
        $this->expectException(CacheCopyFailedException::class);
        $this->expectExceptionMessage(CacheStore::class . '::asOfNow() cannot be called until the instance returned previously is closed or discarded');
        $current = $this->Cache->asOfNow();
    }

    public function testDestructor(): void
    {
        $this->Cache->close();
        $cache = new CacheStore($this->File);
        $cache->set(__METHOD__, 'foo', new DateTimeImmutable('10 seconds ago'));
        $now = time();
        $this->assertSame('foo', $cache->asOfNow($now - 30)->get(__METHOD__));
        unset($cache);
        $cache = new CacheStore($this->File);
        $this->assertNull($cache->asOfNow($now - 30)->get(__METHOD__));
    }

    public function testMultiple(): void
    {
        $this->Cache->setMultiple($values = [
            'key1' => 'value1',
            'key2' => 'value2',
            'key3' => 'value3',
        ]);
        $this->assertEqualsCanonicalizing(['key1', 'key2', 'key3'], $this->Cache->getItemKeys());
        $this->assertSame($values, $this->Cache->getMultiple(['key1', 'key2', 'key3']));
        $this->assertSame(['key0' => 'VALUE', 'key1' => 'value1'], $this->Cache->getMultiple(['key0', 'key1'], 'VALUE'));
        $this->Cache->deleteMultiple(['key1', 'key3']);
        $this->assertSame(['key2'], $this->Cache->getItemKeys());
    }

    public function testGetInstanceOf(): void
    {
        $this->assertNull($this->Cache->getInstanceOf(__METHOD__, stdClass::class));

        $objIn = new stdClass();
        $objIn->Foo = 'bar';
        $this->Cache->set(__METHOD__, $objIn);

        $objOut = $this->Cache->getInstanceOf(__METHOD__, stdClass::class);
        // @phpstan-ignore method.impossibleType
        $this->assertInstanceOf(stdClass::class, $objOut);
        $this->assertEquals($objIn, $objOut);
        $this->assertNotSame($objIn, $objOut);

        $arr = ['foo' => 'bar'];
        $this->Cache->set(__METHOD__, $arr);
        $this->assertNull($this->Cache->getInstanceOf(__METHOD__, stdClass::class));
        $this->assertSame($arr, $this->Cache->get(__METHOD__));
    }

    public function testGetArray(): void
    {
        $this->assertNull($this->Cache->getArray(__METHOD__));
        $this->Cache->set(__METHOD__, $arr = ['foo' => 'bar']);
        $this->assertSame($arr, $this->Cache->getArray(__METHOD__));
        $this->Cache->set(__METHOD__, 'foo');
        $this->assertNull($this->Cache->getArray(__METHOD__));
    }

    public function testGetInt(): void
    {
        $this->assertNull($this->Cache->getInt(__METHOD__));
        $this->Cache->set(__METHOD__, 123);
        $this->assertSame(123, $this->Cache->getInt(__METHOD__));
        $this->Cache->set(__METHOD__, 'foo');
        $this->assertNull($this->Cache->getInt(__METHOD__));
    }

    public function testGetString(): void
    {
        $this->assertNull($this->Cache->getString(__METHOD__));
        $this->Cache->set(__METHOD__, 'foo');
        $this->assertSame('foo', $this->Cache->getString(__METHOD__));
        $this->Cache->set(__METHOD__, 123);
        $this->assertNull($this->Cache->getString(__METHOD__));
    }

    public function testDeleteAll(): void
    {
        $this->Cache->set('key1', 'value1');
        $this->Cache->set('key2', 'value2');
        $this->assertSame('value1', $this->Cache->get('key1'));
        $this->assertSame('value2', $this->Cache->get('key2'));
        $this->assertEqualsCanonicalizing(['key1', 'key2'], $this->Cache->getItemKeys());
        $this->Cache->clear();
        $this->assertNull($this->Cache->get('key1'));
        $this->assertNull($this->Cache->get('key2'));
        $this->assertSame([], $this->Cache->getItemKeys());
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
        File::pruneDir($this->Dir, true);

        unset($this->Cache);
        unset($this->File);
        unset($this->Dir);
    }
}
