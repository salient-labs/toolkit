<?php declare(strict_types=1);

namespace Salient\Sync\Exception;

/**
 * Thrown when a sync operation is unable to resolve its own parameters from the
 * context object it receives
 */
class SyncInvalidContextException extends AbstractSyncException {}
