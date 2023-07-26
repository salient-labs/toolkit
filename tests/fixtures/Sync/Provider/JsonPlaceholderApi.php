<?php declare(strict_types=1);

namespace Lkrms\Tests\Sync\Provider;

use Lkrms\Contract\IServiceSingleton;
use Lkrms\Curler\CurlerHeaders;
use Lkrms\Support\Catalog\ArrayKeyConformity;
use Lkrms\Support\DateFormatter;
use Lkrms\Sync\Catalog\SyncFilterPolicy;
use Lkrms\Sync\Catalog\SyncOperation as OP;
use Lkrms\Sync\Concept\HttpSyncProvider;
use Lkrms\Sync\Support\HttpSyncDefinitionBuilder;
use Lkrms\Sync\Support\SyncContext;
use Lkrms\Tests\Sync\Entity\Post;
use Lkrms\Tests\Sync\Entity\Provider\PostProvider;
use Lkrms\Tests\Sync\Entity\Provider\UserProvider;
use Lkrms\Tests\Sync\Entity\User;

/**
 *
 * @method Post createPost(SyncContext $ctx, Post $post)
 * @method Post getPost(SyncContext $ctx, int|string|null $id)
 * @method Post updatePost(SyncContext $ctx, Post $post)
 * @method Post deletePost(SyncContext $ctx, Post $post)
 * @method iterable<Post> getPosts(SyncContext $ctx)
 * @method User createUser(SyncContext $ctx, User $user)
 * @method User getUser(SyncContext $ctx, int|string|null $id)
 * @method User updateUser(SyncContext $ctx, User $user)
 * @method User deleteUser(SyncContext $ctx, User $user)
 * @method iterable<User> getUsers(SyncContext $ctx)
 */
class JsonPlaceholderApi extends HttpSyncProvider implements PostProvider, UserProvider, IServiceSingleton
{
    private const JSON_PLACEHOLDER_BASE_URL = 'https://jsonplaceholder.typicode.com';

    protected function getBaseUrl(?string $path): string
    {
        return self::JSON_PLACEHOLDER_BASE_URL;
    }

    protected function getHeaders(?string $path): ?CurlerHeaders
    {
        return null;
    }

    public function getBackendIdentifier(): array
    {
        return [self::JSON_PLACEHOLDER_BASE_URL];
    }

    protected function getDateFormatter(): DateFormatter
    {
        return new DateFormatter();
    }

    public function name(): ?string
    {
        return 'JSONPlaceholder {jsonplaceholder.typicode.com}';
    }

    protected function getExpiry(?string $path): ?int
    {
        return 24 * 60 * 60;
    }

    public static function getContextualBindings(): array
    {
        return [
            Post::class => \Lkrms\Tests\Sync\CustomEntity\Post::class,
            User::class => \Lkrms\Tests\Sync\CustomEntity\User::class,
        ];
    }

    protected function buildHttpDefinition(string $entity, HttpSyncDefinitionBuilder $defB): HttpSyncDefinitionBuilder
    {
        switch ($entity) {
            case Post::class:
                return $defB
                    ->operations([OP::READ, OP::READ_LIST])
                    ->path('/posts')
                    ->filterPolicy(SyncFilterPolicy::IGNORE);

            case User::class:
                return $defB
                    ->operations([OP::READ, OP::READ_LIST])
                    ->path('/users')
                    ->filterPolicy(SyncFilterPolicy::IGNORE);
        }

        return $defB;
    }

    public function getPosts(SyncContext $ctx): iterable
    {
        $filter = $ctx->getFilter();
        if ($user = $filter['user'] ?? null) {
            return Post::provideList($this->getCurler("/users/$user/posts")->get(), $this, ArrayKeyConformity::NONE, $ctx);
        }

        return Post::provideList($this->getCurler('/posts')->get(), $this, ArrayKeyConformity::NONE, $ctx);
    }
}
