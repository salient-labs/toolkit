<?php declare(strict_types=1);

namespace Salient\Sync\Exception;

use Salient\Contract\Sync\Exception\InvalidFilterExceptionInterface;

class SyncInvalidFilterException extends AbstractSyncException implements InvalidFilterExceptionInterface {}
