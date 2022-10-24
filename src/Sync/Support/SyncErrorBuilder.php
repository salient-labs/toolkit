<?php

declare(strict_types=1);

namespace Lkrms\Sync\Support;

use Lkrms\Concept\Builder;
use Lkrms\Contract\IContainer;
use Lkrms\Sync\Concept\SyncEntity;
use Lkrms\Sync\Contract\ISyncProvider;

/**
 * A fluent interface for creating SyncError objects
 *
 * @method static $this build(?IContainer $container = null) Create a new SyncErrorBuilder (syntactic sugar for 'new SyncErrorBuilder()')
 * @method static $this errorType(int $value) One of the SyncErrorType values (see {@see SyncError::$ErrorType})
 * @method static $this message(string $value) An sprintf() format string that explains the error (see {@see SyncError::$Message})
 * @method static $this values(array $value) Values passed to sprintf() with the message format string (see {@see SyncError::$Values})
 * @method static $this level(int $value) One of the ConsoleLevel values (see {@see SyncError::$Level})
 * @method static $this entity(?SyncEntity $value) The entity associated with the error (see {@see SyncError::$Entity})
 * @method static $this entityName(?string $value) The display name of the entity associated with the error (see {@see SyncError::$EntityName})
 * @method static $this provider(?ISyncProvider $value) The sync provider associated with the error (see {@see SyncError::$Provider})
 * @method static SyncError go() Return a new SyncError object
 * @method static SyncError resolve(SyncError|SyncErrorBuilder $object) Resolve a SyncErrorBuilder or SyncError object to a SyncError object
 *
 * @uses SyncError
 * @lkrms-generate-command lk-util generate builder --class='Lkrms\Sync\Support\SyncError' --static-builder='build' --terminator='go' --static-resolver='resolve'
 */
final class SyncErrorBuilder extends Builder
{
    /**
     * @internal
     */
    protected static function getClassName(): string
    {
        return SyncError::class;
    }
}
