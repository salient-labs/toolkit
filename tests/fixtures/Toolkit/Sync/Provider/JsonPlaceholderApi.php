<?php declare(strict_types=1);

namespace Salient\Tests\Sync\Provider;

use Salient\Contract\Container\SingletonInterface;
use Salient\Contract\Http\HttpHeadersInterface;
use Salient\Contract\Sync\SyncContextInterface;
use Salient\Contract\Sync\SyncOperation as OP;
use Salient\Core\Facade\Console;
use Salient\Core\DateFormatter;
use Salient\Curler\CurlerBuilder;
use Salient\Sync\HttpSyncDefinitionBuilder;
use Salient\Sync\HttpSyncProvider;
use Salient\Tests\Sync\CustomEntity\Post as CustomPost;
use Salient\Tests\Sync\CustomEntity\User as CustomUser;
use Salient\Tests\Sync\Entity\Provider\AlbumProvider;
use Salient\Tests\Sync\Entity\Provider\CommentProvider;
use Salient\Tests\Sync\Entity\Provider\PhotoProvider;
use Salient\Tests\Sync\Entity\Provider\PostProvider;
use Salient\Tests\Sync\Entity\Provider\TaskProvider;
use Salient\Tests\Sync\Entity\Provider\UserProvider;
use Salient\Tests\Sync\Entity\Album;
use Salient\Tests\Sync\Entity\Comment;
use Salient\Tests\Sync\Entity\Photo;
use Salient\Tests\Sync\Entity\Post;
use Salient\Tests\Sync\Entity\Task;
use Salient\Tests\Sync\Entity\User;
use Salient\Utility\Env;
use LogicException;

/**
 * @method Album createAlbum(SyncContextInterface $ctx, Album $album)
 * @method Album getAlbum(SyncContextInterface $ctx, int|string|null $id)
 * @method Album updateAlbum(SyncContextInterface $ctx, Album $album)
 * @method Album deleteAlbum(SyncContextInterface $ctx, Album $album)
 * @method iterable<Album> getAlbums(SyncContextInterface $ctx)
 * @method Comment createComment(SyncContextInterface $ctx, Comment $comment)
 * @method Comment getComment(SyncContextInterface $ctx, int|string|null $id)
 * @method Comment updateComment(SyncContextInterface $ctx, Comment $comment)
 * @method Comment deleteComment(SyncContextInterface $ctx, Comment $comment)
 * @method iterable<Comment> getComments(SyncContextInterface $ctx)
 * @method Photo createPhoto(SyncContextInterface $ctx, Photo $photo)
 * @method Photo getPhoto(SyncContextInterface $ctx, int|string|null $id)
 * @method Photo updatePhoto(SyncContextInterface $ctx, Photo $photo)
 * @method Photo deletePhoto(SyncContextInterface $ctx, Photo $photo)
 * @method iterable<Photo> getPhotos(SyncContextInterface $ctx)
 * @method Post createPost(SyncContextInterface $ctx, Post $post)
 * @method Post getPost(SyncContextInterface $ctx, int|string|null $id)
 * @method Post updatePost(SyncContextInterface $ctx, Post $post)
 * @method Post deletePost(SyncContextInterface $ctx, Post $post)
 * @method iterable<Post> getPosts(SyncContextInterface $ctx)
 * @method Task createTask(SyncContextInterface $ctx, Task $task)
 * @method Task updateTask(SyncContextInterface $ctx, Task $task)
 * @method Task deleteTask(SyncContextInterface $ctx, Task $task)
 * @method iterable<Task> getTasks(SyncContextInterface $ctx)
 * @method User createUser(SyncContextInterface $ctx, User $user)
 * @method User getUser(SyncContextInterface $ctx, int|string|null $id)
 * @method User updateUser(SyncContextInterface $ctx, User $user)
 * @method User deleteUser(SyncContextInterface $ctx, User $user)
 * @method iterable<User> getUsers(SyncContextInterface $ctx)
 */
class JsonPlaceholderApi extends HttpSyncProvider implements
    SingletonInterface,
    AlbumProvider,
    CommentProvider,
    PhotoProvider,
    PostProvider,
    TaskProvider,
    UserProvider
{
    /** @var array<string,int> */
    public array $HttpRequests = [];

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

    protected function getDateFormatter(?string $path = null): DateFormatter
    {
        return new DateFormatter();
    }

    protected function buildCurler(CurlerBuilder $curlerB): CurlerBuilder
    {
        $uri = $curlerB->getB('uri');

        if (!is_string($uri)) {
            throw new LogicException('Invalid uri');
        }

        if (!isset($this->HttpRequests[$uri])) {
            $this->HttpRequests[$uri] = 0;
        }

        $this->HttpRequests[$uri]++;

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
    public function getTask(SyncContextInterface $ctx, $id): Task
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
