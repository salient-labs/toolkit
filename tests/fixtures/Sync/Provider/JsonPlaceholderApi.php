<?php declare(strict_types=1);

namespace Lkrms\Tests\Sync\Provider;

use Lkrms\Contract\IDateFormatter;
use Lkrms\Contract\IServiceSingleton;
use Lkrms\Curler\Contract\ICurlerHeaders;
use Lkrms\Curler\CurlerBuilder;
use Lkrms\Facade\Cache;
use Lkrms\Facade\Console;
use Lkrms\Iterator\Contract\FluentIteratorInterface;
use Lkrms\Support\Catalog\ArrayKeyConformity;
use Lkrms\Support\DateFormatter;
use Lkrms\Sync\Catalog\SyncFilterPolicy;
use Lkrms\Sync\Catalog\SyncOperation as OP;
use Lkrms\Sync\Concept\HttpSyncProvider;
use Lkrms\Sync\Contract\ISyncContext;
use Lkrms\Sync\Support\HttpSyncDefinitionBuilder;
use Lkrms\Tests\Sync\Entity\Provider\PostProvider;
use Lkrms\Tests\Sync\Entity\Provider\TaskProvider;
use Lkrms\Tests\Sync\Entity\Provider\UserProvider;
use Lkrms\Tests\Sync\Entity\Post;
use Lkrms\Tests\Sync\Entity\Task;
use Lkrms\Tests\Sync\Entity\User;
use Lkrms\Utility\Env;

/**
 * @method Post createPost(ISyncContext $ctx, Post $post)
 * @method Post getPost(ISyncContext $ctx, int|string|null $id)
 * @method Post updatePost(ISyncContext $ctx, Post $post)
 * @method Post deletePost(ISyncContext $ctx, Post $post)
 * @method FluentIteratorInterface<array-key,Post> getPosts(ISyncContext $ctx)
 * @method User createUser(ISyncContext $ctx, User $user)
 * @method User getUser(ISyncContext $ctx, int|string|null $id)
 * @method User updateUser(ISyncContext $ctx, User $user)
 * @method User deleteUser(ISyncContext $ctx, User $user)
 * @method FluentIteratorInterface<array-key,User> getUsers(ISyncContext $ctx)
 * @method Task createTask(ISyncContext $ctx, Task $task)
 * @method Task getTask(ISyncContext $ctx, int|string|null $id)
 * @method Task updateTask(ISyncContext $ctx, Task $task)
 * @method Task deleteTask(ISyncContext $ctx, Task $task)
 * @method FluentIteratorInterface<array-key,Task> getTasks(ISyncContext $ctx)
 */
class JsonPlaceholderApi extends HttpSyncProvider implements
    IServiceSingleton,
    PostProvider,
    TaskProvider,
    UserProvider
{
    /**
     * @var array<string,int>
     */
    public array $HttpRequestCount = [];

    public function name(): ?string
    {
        return sprintf('JSONPlaceholder { %s }', $this->getBaseUrl());
    }

    public static function getContextualBindings(): array
    {
        return [
            Post::class => \Lkrms\Tests\Sync\CustomEntity\Post::class,
            User::class => \Lkrms\Tests\Sync\CustomEntity\User::class,
        ];
    }

    public function getBackendIdentifier(): array
    {
        return [$this->getBaseUrl()];
    }

    public function checkHeartbeat(int $ttl = 300)
    {
        $key = implode(':', [
            static::class,
            __FUNCTION__,
            User::class,
        ]);

        $user = Cache::get($key, $ttl);
        if ($user === false) {
            $user = $this->with(User::class)->get(1);
            Cache::set($key, $user, $ttl);
        }

        Console::info(sprintf(
            'Connected to %s as %s',
            $this->name(),
            $user->name(),
        ));
    }

    protected function getDateFormatter(?string $path = null): IDateFormatter
    {
        return new DateFormatter();
    }

    protected function buildCurler(CurlerBuilder $curlerB): CurlerBuilder
    {
        $baseUrl = $curlerB->getB('baseUrl');
        if (!isset($this->HttpRequestCount[$baseUrl])) {
            $this->HttpRequestCount[$baseUrl] = 0;
        }

        $this->HttpRequestCount[$baseUrl]++;

        return $curlerB;
    }

    protected function getBaseUrl(?string $path = null): string
    {
        // Set JSON_PLACEHOLDER_BASE_URL=https://jsonplaceholder.typicode.com to
        // test against the live version if necessary
        return Env::get('JSON_PLACEHOLDER_BASE_URL', 'http://localhost:3001');
    }

    protected function getHeaders(?string $path): ?ICurlerHeaders
    {
        return null;
    }

    protected function buildHttpDefinition(string $entity, HttpSyncDefinitionBuilder $defB): HttpSyncDefinitionBuilder
    {
        switch ($entity) {
            case Post::class:
                return $defB
                    ->operations([OP::READ, OP::READ_LIST])
                    ->path(['/users/:userId/posts', '/posts']);

            case User::class:
                return $defB
                    ->operations([OP::READ, OP::READ_LIST])
                    ->path('/users')
                    ->filterPolicy(SyncFilterPolicy::IGNORE);
        }

        return $defB;
    }

    public function getTasks(ISyncContext $ctx): FluentIteratorInterface
    {
        return Task::provideList($this->getCurler('/todos')->get(), $this, ArrayKeyConformity::NONE, $ctx);
    }
}
