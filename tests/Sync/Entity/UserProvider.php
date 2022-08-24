<?php

declare(strict_types=1);

namespace Lkrms\Tests\Sync\Entity;

/**
 * Syncs User objects with a backend
 *
 * @lkrms-generate-command lk-util generate sync provider --class='Lkrms\Tests\Sync\Entity\User' --op='create,get,update,delete,get-list'
 */
interface UserProvider extends \Lkrms\Sync\Provider\ISyncProvider
{
    /**
     * @param User $user
     * @return User
     */
    public function createUser(User $user): User;

    /**
     * @param int|string $id
     * @return User
     */
    public function getUser($id): User;

    /**
     * @param User $user
     * @return User
     */
    public function updateUser(User $user): User;

    /**
     * @param User $user
     * @return null|User
     */
    public function deleteUser(User $user): ?User;

    /**
     * @return iterable<User>
     */
    public function getUsers(): iterable;

}
