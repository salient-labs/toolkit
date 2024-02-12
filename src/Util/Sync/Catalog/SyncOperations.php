<?php declare(strict_types=1);

namespace Lkrms\Sync\Catalog;

use Lkrms\Concept\Enumeration;

/**
 * Groups of sync operation types
 *
 * @extends Enumeration<int[]>
 */
final class SyncOperations extends Enumeration
{
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

    public const ALL_LIST = [
        SyncOperation::CREATE_LIST,
        SyncOperation::READ_LIST,
        SyncOperation::UPDATE_LIST,
        SyncOperation::DELETE_LIST,
    ];

    public const ALL_READ = [
        SyncOperation::READ,
        SyncOperation::READ_LIST,
    ];

    public const ALL_WRITE = [
        SyncOperation::CREATE,
        SyncOperation::UPDATE,
        SyncOperation::DELETE,
        SyncOperation::CREATE_LIST,
        SyncOperation::UPDATE_LIST,
        SyncOperation::DELETE_LIST,
    ];
}
