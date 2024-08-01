<?php declare(strict_types=1);

namespace Salient\Sync\Exception;

use Salient\Contract\Sync\Exception\SyncExceptionInterface;
use Salient\Core\AbstractException;

/** @internal */
abstract class AbstractSyncException extends AbstractException implements SyncExceptionInterface {}
