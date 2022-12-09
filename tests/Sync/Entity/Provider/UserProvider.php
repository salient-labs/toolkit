<?php declare(strict_types=1);

namespace Lkrms\Tests\Sync\Entity\Provider;

use Lkrms\Sync\Contract\ISyncProvider;
use Lkrms\Sync\Support\SyncContext;
use Lkrms\Tests\Sync\Entity\User;

/**
 * Syncs User objects with a backend
 *
 * @method User createUser(SyncContext $ctx, User $user)
 * @method User getUser(SyncContext $ctx, int|string|null $id)
 * @method User updateUser(SyncContext $ctx, User $user)
 * @method User deleteUser(SyncContext $ctx, User $user)
 * @method iterable<User> getUsers(SyncContext $ctx)
 *
 * @lkrms-generate-command lk-util generate sync provider --magic --op='create,get,update,delete,get-list' 'Lkrms\Tests\Sync\Entity\User'
 */
interface UserProvider extends ISyncProvider
{
}
