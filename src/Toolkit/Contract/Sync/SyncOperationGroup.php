<?php declare(strict_types=1);

namespace Salient\Contract\Sync;

use Salient\Core\AbstractDictionary;

/**
 * Groups of sync operation types
 *
 * @extends AbstractDictionary<list<SyncOperation::*>>
 */
final class SyncOperationGroup extends AbstractDictionary
{
    /**
     * @var list<SyncOperation::*>
     */
    public const ALL = [
        SyncOperation::CREATE,
        SyncOperation::READ,
        SyncOperation::UPDATE,
        SyncOperation::DELETE,
        SyncOperation::CREATE_LIST,
        SyncOperation::READ_LIST,
        SyncOperation::UPDATE_LIST,
        SyncOperation::DELETE_LIST,
    ];

    /**
     * @var list<SyncOperation::*>
     */
    public const ALL_LIST = [
        SyncOperation::CREATE_LIST,
        SyncOperation::READ_LIST,
        SyncOperation::UPDATE_LIST,
        SyncOperation::DELETE_LIST,
    ];

    /**
     * @var list<SyncOperation::*>
     */
    public const ALL_READ = [
        SyncOperation::READ,
        SyncOperation::READ_LIST,
    ];

    /**
     * @var list<SyncOperation::*>
     */
    public const ALL_WRITE = [
        SyncOperation::CREATE,
        SyncOperation::UPDATE,
        SyncOperation::DELETE,
        SyncOperation::CREATE_LIST,
        SyncOperation::UPDATE_LIST,
        SyncOperation::DELETE_LIST,
    ];
}
