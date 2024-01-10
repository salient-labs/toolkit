<?php declare(strict_types=1);

namespace Lkrms\Tests\Sync\Provider;

use Lkrms\Contract\IServiceSingleton;
use Lkrms\Curler\CurlerBuilder;
use Lkrms\Facade\Console;
use Lkrms\Http\Contract\HttpHeadersInterface;
use Lkrms\Support\Date\DateFormatter;
use Lkrms\Support\Date\DateFormatterInterface;
use Lkrms\Sync\Catalog\SyncOperation as OP;
use Lkrms\Sync\Concept\HttpSyncProvider;
use Lkrms\Sync\Contract\ISyncContext;
use Lkrms\Sync\Support\HttpSyncDefinitionBuilder;
use Lkrms\Tests\Sync\CustomEntity\Post as CustomPost;
use Lkrms\Tests\Sync\CustomEntity\User as CustomUser;
use Lkrms\Tests\Sync\Entity\Provider\AlbumProvider;
use Lkrms\Tests\Sync\Entity\Provider\CommentProvider;
use Lkrms\Tests\Sync\Entity\Provider\PhotoProvider;
use Lkrms\Tests\Sync\Entity\Provider\PostProvider;
use Lkrms\Tests\Sync\Entity\Provider\TaskProvider;
use Lkrms\Tests\Sync\Entity\Provider\UserProvider;
use Lkrms\Tests\Sync\Entity\Album;
use Lkrms\Tests\Sync\Entity\Comment;
use Lkrms\Tests\Sync\Entity\Photo;
use Lkrms\Tests\Sync\Entity\Post;
use Lkrms\Tests\Sync\Entity\Task;
use Lkrms\Tests\Sync\Entity\User;
use Lkrms\Utility\Env;

/**
 * @method Album createAlbum(ISyncContext $ctx, Album $album)
 * @method Album getAlbum(ISyncContext $ctx, int|string|null $id)
 * @method Album updateAlbum(ISyncContext $ctx, Album $album)
 * @method Album deleteAlbum(ISyncContext $ctx, Album $album)
 * @method iterable<Album> getAlbums(ISyncContext $ctx)
 * @method Comment createComment(ISyncContext $ctx, Comment $comment)
 * @method Comment getComment(ISyncContext $ctx, int|string|null $id)
 * @method Comment updateComment(ISyncContext $ctx, Comment $comment)
 * @method Comment deleteComment(ISyncContext $ctx, Comment $comment)
 * @method iterable<Comment> getComments(ISyncContext $ctx)
 * @method Photo createPhoto(ISyncContext $ctx, Photo $photo)
 * @method Photo getPhoto(ISyncContext $ctx, int|string|null $id)
 * @method Photo updatePhoto(ISyncContext $ctx, Photo $photo)
 * @method Photo deletePhoto(ISyncContext $ctx, Photo $photo)
 * @method iterable<Photo> getPhotos(ISyncContext $ctx)
 * @method Post createPost(ISyncContext $ctx, Post $post)
 * @method Post getPost(ISyncContext $ctx, int|string|null $id)
 * @method Post updatePost(ISyncContext $ctx, Post $post)
 * @method Post deletePost(ISyncContext $ctx, Post $post)
 * @method iterable<Post> getPosts(ISyncContext $ctx)
 * @method Task createTask(ISyncContext $ctx, Task $task)
 * @method Task updateTask(ISyncContext $ctx, Task $task)
 * @method Task deleteTask(ISyncContext $ctx, Task $task)
 * @method iterable<Task> getTasks(ISyncContext $ctx)
 * @method User createUser(ISyncContext $ctx, User $user)
 * @method User getUser(ISyncContext $ctx, int|string|null $id)
 * @method User updateUser(ISyncContext $ctx, User $user)
 * @method User deleteUser(ISyncContext $ctx, User $user)
 * @method iterable<User> getUsers(ISyncContext $ctx)
 */
class JsonPlaceholderApi extends HttpSyncProvider implements
    IServiceSingleton,
    AlbumProvider,
    CommentProvider,
    PhotoProvider,
    PostProvider,
    TaskProvider,
    UserProvider
{
    /**
     * @var array<string,int>
     */
    public array $HttpRequestCount = [];

    public function name(): string
    {
        return sprintf('JSONPlaceholder { %s }', $this->getBaseUrl());
    }

    public static function getContextualBindings(): array
    {
        return [
            Post::class => CustomPost::class,
            User::class => CustomUser::class,
        ];
    }

    public function getBackendIdentifier(): array
    {
        return [$this->getBaseUrl()];
    }

    protected function getHeartbeat()
    {
        $user = $this->with(User::class)->doNotHydrate()->get(1);

        Console::info(sprintf(
            'Connected to %s as %s',
            $this->name(),
            $user->name(),
        ));

        return $user;
    }

    protected function getDateFormatter(?string $path = null): DateFormatterInterface
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

    public function getBaseUrl(?string $path = null): string
    {
        // Set JSON_PLACEHOLDER_BASE_URL=https://jsonplaceholder.typicode.com to
        // test against the live version if necessary
        return Env::get('JSON_PLACEHOLDER_BASE_URL', 'http://localhost:3001');
    }

    protected function getHeaders(?string $path): ?HttpHeadersInterface
    {
        return null;
    }

    protected function buildHttpDefinition(string $entity, HttpSyncDefinitionBuilder $defB): HttpSyncDefinitionBuilder
    {
        switch ($entity) {
            case Album::class:
                return $defB
                    ->operations([OP::READ, OP::READ_LIST])
                    ->path(['/users/:userId/albums', '/albums']);

            case Comment::class:
                return $defB
                    ->operations([OP::READ, OP::READ_LIST])
                    ->path(['/posts/:postId/comments', '/comments']);

            case Photo::class:
                return $defB
                    ->operations([OP::READ, OP::READ_LIST])
                    ->path(['/albums/:albumId/photos', '/photos']);

            case Post::class:
                return $defB
                    ->operations([OP::READ, OP::READ_LIST])
                    ->path(['/users/:userId/posts', '/posts']);

            case User::class:
                return $defB
                    ->operations([OP::READ, OP::READ_LIST])
                    ->path('/users');

            case Task::class:
                return $defB
                    ->operations([OP::READ, OP::READ_LIST])
                    ->path(['/users/:userId/todos', '/todos']);
        }

        return $defB;
    }

    /**
     * @param int|string|null $id
     */
    public function getTask(ISyncContext $ctx, $id): Task
    {
        return $this->run(
            $ctx,
            fn(): Task =>
                Task::provide(
                    $this->getCurler(sprintf('/todos/%s', rawurlencode($id)))->get(),
                    $this,
                    $ctx,
                )
        );
    }
}
