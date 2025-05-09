<?php declare(strict_types=1);

namespace Salient\Tests\Sync\Provider;

use Salient\Contract\Container\ContainerInterface;
use Salient\Contract\Container\SingletonInterface;
use Salient\Contract\Curler\CurlerInterface;
use Salient\Contract\Http\HeadersInterface;
use Salient\Contract\Sync\SyncContextInterface;
use Salient\Contract\Sync\SyncOperation as OP;
use Salient\Core\Facade\Console;
use Salient\Sync\Http\HttpSyncDefinition;
use Salient\Sync\Http\HttpSyncProvider;
use Salient\Tests\Sync\CustomEntity\Post as CustomPost;
use Salient\Tests\Sync\CustomEntity\User as CustomUser;
use Salient\Tests\Sync\Entity\Provider\AlbumProvider;
use Salient\Tests\Sync\Entity\Provider\CollidesProvider;
use Salient\Tests\Sync\Entity\Provider\CommentProvider;
use Salient\Tests\Sync\Entity\Provider\PhotoProvider;
use Salient\Tests\Sync\Entity\Provider\PostProvider;
use Salient\Tests\Sync\Entity\Provider\TaskProvider;
use Salient\Tests\Sync\Entity\Provider\UserProvider;
use Salient\Tests\Sync\Entity\Album;
use Salient\Tests\Sync\Entity\Collides;
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
 * @method iterable<array-key,Album> getAlbums(SyncContextInterface $ctx)
 * @method Comment createComment(SyncContextInterface $ctx, Comment $comment)
 * @method Comment getComment(SyncContextInterface $ctx, int|string|null $id)
 * @method Comment updateComment(SyncContextInterface $ctx, Comment $comment)
 * @method Comment deleteComment(SyncContextInterface $ctx, Comment $comment)
 * @method iterable<array-key,Comment> getComments(SyncContextInterface $ctx)
 * @method Photo createPhoto(SyncContextInterface $ctx, Photo $photo)
 * @method Photo getPhoto(SyncContextInterface $ctx, int|string|null $id)
 * @method Photo updatePhoto(SyncContextInterface $ctx, Photo $photo)
 * @method Photo deletePhoto(SyncContextInterface $ctx, Photo $photo)
 * @method iterable<array-key,Photo> getPhotos(SyncContextInterface $ctx)
 * @method Post createPost(SyncContextInterface $ctx, Post $post)
 * @method Post getPost(SyncContextInterface $ctx, int|string|null $id)
 * @method Post updatePost(SyncContextInterface $ctx, Post $post)
 * @method Post deletePost(SyncContextInterface $ctx, Post $post)
 * @method iterable<array-key,Post> getPosts(SyncContextInterface $ctx)
 * @method Task createTask(SyncContextInterface $ctx, Task $task)
 * @method Task updateTask(SyncContextInterface $ctx, Task $task)
 * @method Task deleteTask(SyncContextInterface $ctx, Task $task)
 * @method iterable<array-key,Task> getTasks(SyncContextInterface $ctx)
 * @method User createUser(SyncContextInterface $ctx, User $user)
 * @method User getUser(SyncContextInterface $ctx, int|string|null $id)
 * @method User updateUser(SyncContextInterface $ctx, User $user)
 * @method User deleteUser(SyncContextInterface $ctx, User $user)
 * @method iterable<array-key,User> getUsers(SyncContextInterface $ctx)
 * @method Collides createCollides(SyncContextInterface $ctx, Collides $collides)
 * @method Collides getCollides(SyncContextInterface $ctx, int|string|null $id)
 * @method Collides updateCollides(SyncContextInterface $ctx, Collides $collides)
 * @method Collides deleteCollides(SyncContextInterface $ctx, Collides $collides)
 * @method iterable<array-key,Collides> getCollideses(SyncContextInterface $ctx)
 */
class JsonPlaceholderApi extends HttpSyncProvider implements
    SingletonInterface,
    AlbumProvider,
    CommentProvider,
    PhotoProvider,
    PostProvider,
    TaskProvider,
    UserProvider,
    CollidesProvider
{
    /** @var array<string,int> */
    public array $HttpRequests = [];

    public function getName(): string
    {
        return sprintf('JSONPlaceholder { %s }', $this->getBaseUrl());
    }

    public static function getContextualBindings(ContainerInterface $container): array
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

        Console::message(sprintf(
            'Connected to %s as %s',
            $this->getName(),
            $user->getName(),
        ));

        return $user;
    }

    protected function filterCurler(CurlerInterface $curler, string $path): CurlerInterface
    {
        $uri = (string) $curler->getUri();
        $this->HttpRequests[$uri] ??= 0;
        $this->HttpRequests[$uri]++;

        return $curler->withPager(new MockoonPager());
    }

    public function getBaseUrl(?string $path = null): string
    {
        // Set JSON_PLACEHOLDER_BASE_URL=https://jsonplaceholder.typicode.com to
        // test against the live version if necessary
        return Env::get('JSON_PLACEHOLDER_BASE_URL', 'http://localhost:3001');
    }

    protected function getHeaders(string $path): ?HeadersInterface
    {
        return null;
    }

    protected function getHttpDefinition(string $entity): HttpSyncDefinition
    {
        $defB = $this
            ->builderFor($entity)
            ->operations([OP::READ, OP::READ_LIST]);

        switch ($entity) {
            case Album::class:
                return $defB->path(['/users/:userId/albums', '/albums'])->build();

            case Comment::class:
                return $defB->path(['/posts/:postId/comments', '/comments'])->build();

            case Photo::class:
                return $defB->path(['/albums/:albumId/photos', '/photos'])->build();

            case Post::class:
                return $defB->path(['/users/:userId/posts', '/posts'])->build();

            case Task::class:
                return $defB->path(['/users/:userId/todos', '/todos'])->build();

            case User::class:
                return $defB->path('/users')->build();
        }

        throw new LogicException(sprintf('Entity not supported: %s', $entity));
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
                    $ctx,
                )
        );
    }
}
