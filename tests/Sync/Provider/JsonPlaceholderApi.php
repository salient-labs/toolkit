<?php

declare(strict_types=1);

namespace Lkrms\Tests\Sync\Provider;

use Lkrms\Container\Container;
use Lkrms\Curler\CurlerHeaders;
use Lkrms\Exception\SyncOperationNotImplementedException;
use Lkrms\Support\DateFormatter;
use Lkrms\Sync\Provider\HttpSyncProvider;
use Lkrms\Sync\SyncOperation;
use Lkrms\Tests\Sync\Entity\Post;
use Lkrms\Tests\Sync\Entity\PostProvider;
use Lkrms\Tests\Sync\Entity\User;
use Lkrms\Tests\Sync\Entity\UserProvider;

class JsonPlaceholderApi extends HttpSyncProvider implements PostProvider, UserProvider
{
    private const JSON_PLACEHOLDER_BASE_URL = "https://jsonplaceholder.typicode.com";

    protected function getBaseUrl(?string $path): string
    {
        return self::JSON_PLACEHOLDER_BASE_URL;
    }

    protected function getHeaders(?string $path): ?CurlerHeaders
    {
        return null;
    }

    protected function getBackendIdentifier(): array
    {
        return [self::JSON_PLACEHOLDER_BASE_URL];
    }

    protected function _getDateFormatter(): DateFormatter
    {
        return new DateFormatter();
    }

    protected function getCacheExpiry(): ?int
    {
        return 24 * 60 * 60;
    }

    public static function getBindings(): array
    {
        return [
            Post::class => \Lkrms\Tests\Sync\CustomEntity\Post::class,
            User::class => \Lkrms\Tests\Sync\CustomEntity\User::class,
        ];
    }

    public function createPost(Post $post): Post
    {
        throw new SyncOperationNotImplementedException(self::class, Post::class, SyncOperation::CREATE);
    }

    public function getPost($id): Post
    {
        return Post::fromProvider($this, $this->getCurler("/posts/" . $id)->getJson());
    }

    public function updatePost(Post $post): Post
    {
        throw new SyncOperationNotImplementedException(self::class, Post::class, SyncOperation::UPDATE);
    }

    public function deletePost(Post $post): ?Post
    {
        throw new SyncOperationNotImplementedException(self::class, Post::class, SyncOperation::DELETE);
    }

    public function getPosts(): iterable
    {
        $filter   = $this->getListFilter(func_get_args());
        if ($user = $filter["user"] ?? null)
        {
            return Post::listFromProvider($this, $this->getCurler("/users/$user/posts")->getJson());
        }
        return Post::listFromProvider($this, $this->getCurler("/posts")->getJson());
    }

    public function createUser(User $user): User
    {
        throw new SyncOperationNotImplementedException(self::class, User::class, SyncOperation::CREATE);
    }

    public function getUser($id): User
    {
        return User::fromProvider($this, $this->getCurler("/users/" . $id)->getJson());
    }

    public function updateUser(User $user): User
    {
        throw new SyncOperationNotImplementedException(self::class, User::class, SyncOperation::UPDATE);
    }

    public function deleteUser(User $user): ?User
    {
        throw new SyncOperationNotImplementedException(self::class, User::class, SyncOperation::DELETE);
    }

    public function getUsers(): iterable
    {
        return User::listFromProvider($this, $this->getCurler("/users")->getJson());
    }
}
