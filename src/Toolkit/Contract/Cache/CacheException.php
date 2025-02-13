<?php declare(strict_types=1);

namespace Salient\Contract\Cache;

use Psr\SimpleCache\CacheException as PsrCacheException;
use Throwable;

/**
 * @api
 */
interface CacheException extends PsrCacheException, Throwable {}
