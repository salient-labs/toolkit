<?php

declare(strict_types=1);

namespace Lkrms\Tests\Sync\Api;

use Lkrms\Curler\CurlerHeaders;
use Lkrms\Sync\Provider\HttpSyncProvider;
use Lkrms\Tests\Sync\Entity\Post;
use Lkrms\Tests\Sync\Entity\PostProvider;
use Lkrms\Tests\Sync\Entity\User;
use Lkrms\Tests\Sync\Entity\UserProvider;
use RuntimeException;

class JsonPlaceholderApi extends HttpSyncProvider implements PostProvider, UserProvider
{
    private const JSON_PLACEHOLDER_BASE_URL = "https://jsonplaceholder.typicode.com";

    protected function getBaseUrl(): string
    {
        return self::JSON_PLACEHOLDER_BASE_URL;
    }

    protected function getHeaders(): ?CurlerHeaders
    {
        return null;
    }

    protected function getBackendIdentifier(): array
    {
        return [self::JSON_PLACEHOLDER_BASE_URL];
    }

    public function createPost(Post $post): Post
    {
        throw new RuntimeException("Not implemented");
    }

    public function getPost($id): Post
    {
        return Post::from($this->getCurler("/posts/" . $id)->GetJson());
    }

    public function updatePost(Post $post): Post
    {
        throw new RuntimeException("Not implemented");
    }

    public function deletePost(Post $post): ?Post
    {
        throw new RuntimeException("Not implemented");
    }

    public function getPosts(): array
    {
        return Post::listFrom($this->getCurler("/posts")->GetJson());
    }

    public function createUser(User $user): User
    {
        throw new RuntimeException("Not implemented");
    }

    public function getUser($id): User
    {
        return User::from($this->getCurler("/users/" . $id)->GetJson());
    }

    public function updateUser(User $user): User
    {
        throw new RuntimeException("Not implemented");
    }

    public function deleteUser(User $user): ?User
    {
        throw new RuntimeException("Not implemented");
    }

    public function getUsers(): array
    {
        return User::listFrom($this->getCurler("/users")->GetJson());
    }
}

