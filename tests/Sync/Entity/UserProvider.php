<?php

declare(strict_types=1);

namespace Lkrms\Tests\Sync\Entity;

interface UserProvider
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
     * @return User[]
     */
    public function getUsers(): array;
}
