<?php

declare(strict_types=1);

namespace Lkrms\Tests\Sync\Entity;

/**
 * Syncs User objects with a backend
 *
 * @method User createUser(User $user)
 * @method User getUser(int|string $id)
 * @method User updateUser(User $user)
 * @method User|null deleteUser(User $user)
 * @method iterable<User> getUsers()
 *
 * @lkrms-generate-command lk-util generate sync provider --class='Lkrms\Tests\Sync\Entity\User' --op='create,get,update,delete,get-list'
 */
interface UserProvider extends \Lkrms\Sync\Contract\ISyncProvider
{
}
