<?php

declare(strict_types=1);

namespace Lkrms\Tests\Sync\Api;

use Lkrms\Curler\Curler;
use Lkrms\Sync\SyncProvider;
use Lkrms\Tests\Sync\Entity\Post;
use Lkrms\Tests\Sync\Entity\PostProvider;
use Lkrms\Tests\Sync\Entity\User;
use Lkrms\Tests\Sync\Entity\UserProvider;
use RuntimeException;

class JsonPlaceholderApi extends SyncProvider implements PostProvider, UserProvider
{
    private const JSON_PLACEHOLDER_BASE_URL = "https://jsonplaceholder.typicode.com";

    private function getCurler(string $path): Curler
    {
        return new Curler(self::JSON_PLACEHOLDER_BASE_URL . $path);
    }

    public function getBackendIdentifier(): string
    {
        return self::JSON_PLACEHOLDER_BASE_URL;
    }

    public function createPost(Post $post): Post
    {
        throw new RuntimeException("Not implemented");
    }

    public function getPost($id): Post
    {
        return Post::From($this->getCurler("/posts/" . $id)->GetJson());
    }

    public function updatePost(Post $post): Post
    {
        throw new RuntimeException("Not implemented");
    }

    public function deletePost(Post $post): ?Post
    {
        throw new RuntimeException("Not implemented");
    }

    public function listPost(): array
    {
        return Post::ListFrom($this->getCurler("/posts")->GetJson());
    }

    public function createUser(User $user): User
    {
        throw new RuntimeException("Not implemented");
    }

    public function getUser($id): User
    {
        return User::From($this->getCurler("/users/" . $id)->GetJson());
    }

    public function updateUser(User $user): User
    {
        throw new RuntimeException("Not implemented");
    }

    public function deleteUser(User $user): ?User
    {
        throw new RuntimeException("Not implemented");
    }

    public function listUser(): array
    {
        return User::ListFrom($this->getCurler("/users")->GetJson());
    }
}

