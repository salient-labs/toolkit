<?php

declare(strict_types=1);

namespace Lkrms\Sync\Contract;

use Closure;

/**
 * Provides access to an ISyncProvider's implementation of sync operations for
 * an entity
 *
 */
interface ISyncDefinition
{
    /**
     * Return a closure that uses the provider to perform a sync operation on
     * the entity
     *
     * Closure signatures vary by operation, but the first value passed is
     * always the current {@see \Lkrms\Sync\Support\SyncContext}, and optional
     * parameters may be added after required ones. A full signature might be:
     *
     * ```php
     * fn(SyncContext $ctx, SyncEntity $entity, ...$args): SyncEntity
     * ```
     *
     * For clarity, the {@see \Lkrms\Sync\Support\SyncContext} and variadic
     * `$args` parameters have been removed here:
     *
     * | Operation[^op] | Signature                            | Equivalent `SyncProvider` method[^1]        | Alternative `SyncProvider` method[^2][^3]         |
     * | -------------- | ------------------------------------ | ------------------------------------------- | ------------------------------------------------- |
     * | `CREATE`       | `fn(SyncEntity $entity): SyncEntity` | `createUser(User $entity): User`            | `create_Series(Series $entity): Series`           |
     * | `READ`         | `fn(int $id = null): SyncEntity`     | `getUser(int $id = null): User`             | `get_Series(int $id = null): Series`              |
     * | `UPDATE`       | `fn(SyncEntity $entity): SyncEntity` | `updateUser(User $entity): User`            | `update_Series(Series $entity): Series`           |
     * | `DELETE`       | `fn(SyncEntity $entity): SyncEntity` | `deleteUser(User $entity): User`            | `delete_Series(Series $entity): Series`           |
     * | `CREATE_LIST`  | `fn(iterable $entities): iterable`   | `createUsers(iterable $entities): iterable` | `createList_Series(iterable $entities): iterable` |
     * | `READ_LIST`    | `fn(): iterable`                     | `getUsers(): iterable`                      | `getList_Series(): iterable`                      |
     * | `UPDATE_LIST`  | `fn(iterable $entities): iterable`   | `updateUsers(iterable $entities): iterable` | `updateList_Series(iterable $entities): iterable` |
     * | `DELETE_LIST`  | `fn(iterable $entities): iterable`   | `deleteUsers(iterable $entities): iterable` | `deleteList_Series(iterable $entities): iterable` |
     *
     * [^op]: See {@see \Lkrms\Sync\SyncOperation}.
     *
     * [^1]: Examples only. For a {@see \Lkrms\Sync\SyncEntity} subclass called
     * `User`. Method names must match the unqualified name of the entity they
     * operate on.
     *
     * [^2]: Recommended when the singular and plural forms of a class name are
     * the same.
     *
     * [^3]: Examples only. For a {@see \Lkrms\Sync\SyncEntity} subclass called
     * `Series`. Method names must match the unqualified name of the entity they
     * operate on.
     *
     * @return Closure|null `null` if `$operation` is not supported, otherwise a
     * closure with the correct signature for the sync operation.
     */
    public function getSyncOperationClosure(int $operation): ?Closure;

}
