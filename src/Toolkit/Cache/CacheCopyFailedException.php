<?php declare(strict_types=1);

namespace Salient\Cache;

use Salient\Contract\Cache\CacheCopyFailedException as CacheCopyFailedExceptionInterface;
use LogicException;

/**
 * @internal
 */
class CacheCopyFailedException extends LogicException implements CacheCopyFailedExceptionInterface {}
