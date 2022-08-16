<?php

declare(strict_types=1);

namespace Lkrms\Facade;

use Lkrms\Concept\Facade;
use Lkrms\Store\CacheStore;

/**
 * A facade for CacheStore
 *
 * @method static CacheStore load(string $filename = ':memory:', bool $autoFlush = true)
 * @method static CacheStore close()
 * @method static CacheStore delete(string $key)
 * @method static CacheStore flush()
 * @method static CacheStore flushExpired()
 * @method static mixed get(string $key, ?int $maxAge = null)
 * @method static ?string getFilename()
 * @method static bool isOpen()
 * @method static mixed maybeGet(string $key, callable $callback, int $expiry = 0)
 * @method static CacheStore open(string $filename = ':memory:', bool $autoFlush = true)
 * @method static CacheStore set(string $key, mixed $value, int $expiry = 0)
 *
 * @uses CacheStore
 * @lkrms-generate-command lk-util generate facade --class='Lkrms\Store\CacheStore' --generate='Lkrms\Facade\Cache'
 */
final class Cache extends Facade
{
    protected static function getServiceName(): string
    {
        return CacheStore::class;
    }
}
