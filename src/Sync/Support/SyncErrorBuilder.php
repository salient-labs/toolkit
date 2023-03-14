<?php declare(strict_types=1);

namespace Lkrms\Sync\Support;

use Lkrms\Concept\Builder;
use Lkrms\Contract\IContainer;
use Lkrms\Sync\Concept\SyncEntity;
use Lkrms\Sync\Contract\ISyncProvider;

/**
 * A fluent interface for creating SyncError objects
 *
 * @method static $this build(?IContainer $container = null) Create a new SyncErrorBuilder (syntactic sugar for 'new SyncErrorBuilder()')
 * @method $this errorType(int $value) One of the SyncErrorType values (see {@see SyncError::$ErrorType})
 * @method $this message(string $value) An sprintf() format string that explains the error (see {@see SyncError::$Message})
 * @method $this values(array $value) Values passed to sprintf() with the message format string (see {@see SyncError::$Values})
 * @method $this level(int $value) One of the ConsoleLevel values (see {@see SyncError::$Level})
 * @method $this entity(?SyncEntity $value) The entity associated with the error
 * @method $this entityName(?string $value) The display name of the entity associated with the error (see {@see SyncError::$EntityName})
 * @method $this provider(?ISyncProvider $value) The sync provider associated with the error
 * @method mixed get(string $name) The value of $name if applied to the unresolved SyncError by calling $name(), otherwise null
 * @method bool isset(string $name) True if a value for $name has been applied to the unresolved SyncError by calling $name()
 * @method SyncError go() Get a new SyncError object
 * @method static SyncError|null resolve(SyncError|SyncErrorBuilder|null $object) Resolve a SyncErrorBuilder or SyncError object to a SyncError object
 *
 * @uses SyncError
 * @lkrms-generate-command lk-util generate builder --static-builder=build --value-getter=get --value-checker=isset --terminator=go --static-resolver=resolve 'Lkrms\Sync\Support\SyncError'
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
