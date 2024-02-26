<?php declare(strict_types=1);

namespace Salient\Tests\Sync\Support;

use Salient\Sync\Catalog\HydrationPolicy;
use Salient\Sync\Support\DeferredRelationship;
use Salient\Tests\Sync\Entity\Provider\AlbumProvider;
use Salient\Tests\Sync\Entity\Provider\UserProvider;
use Salient\Tests\Sync\Entity\Album;
use Salient\Tests\Sync\Entity\Photo;
use Salient\Tests\Sync\Entity\Post;
use Salient\Tests\Sync\Entity\Task;
use Salient\Tests\Sync\Entity\User;
use Salient\Tests\Sync\SyncTestCase;

final class DeferredRelationshipTest extends SyncTestCase
{
    public function testLazyHydration(): void
    {
        $provider = $this->App->get(UserProvider::class);
        $context = $provider
            ->getContext()
            ->withHydrationPolicy(HydrationPolicy::LAZY);

        $user = $provider->with(User::class, $context)->get(1);
        $this->assertInstanceOf(DeferredRelationship::class, $user->Posts);

        foreach ($user->Posts as $post) {
            break;
        }
        $this->assertIsArray($user->Posts);
        $this->assertCount(10, $user->Posts);
        $this->assertContainsOnlyInstancesOf(Post::class, $user->Posts);
        // @phpstan-ignore-next-line
        $this->assertSame($post, $user->Posts[0]);
        $this->assertSame($user, $user->Posts[0]->User);
        $this->assertSame('sunt aut facere repellat provident occaecati excepturi optio reprehenderit', $post->Title);

        $this->assertHttpRequestCounts([
            '/users/1' => 1,
            '/users/1/posts' => 1,
        ]);
    }

    public function testEagerHydration(): void
    {
        $provider = $this->App->get(UserProvider::class);
        $context = $provider
            ->getContext()
            ->withHydrationPolicy(HydrationPolicy::EAGER);

        $user = $provider->with(User::class, $context)->get(1);
        $this->assertIsArray($user->Posts);
        $this->assertCount(10, $user->Posts);
        $this->assertContainsOnlyInstancesOf(Post::class, $user->Posts);
        $this->assertSame($user, $user->Posts[0]->User);
        $this->assertSame('sunt aut facere repellat provident occaecati excepturi optio reprehenderit', $user->Posts[0]->Title);

        $this->assertHttpRequestCounts([
            '/users/1' => 1,
            '/users/1/todos' => 1,
            '/users/1/posts' => 1,
            '/posts/1/comments' => 1,
            '/posts/2/comments' => 1,
            '/posts/3/comments' => 1,
            '/posts/4/comments' => 1,
            '/posts/5/comments' => 1,
            '/posts/6/comments' => 1,
            '/posts/7/comments' => 1,
            '/posts/8/comments' => 1,
            '/posts/9/comments' => 1,
            '/posts/10/comments' => 1,
            '/users/1/albums' => 1,
            '/albums/1/photos' => 1,
            '/albums/2/photos' => 1,
            '/albums/3/photos' => 1,
            '/albums/4/photos' => 1,
            '/albums/5/photos' => 1,
            '/albums/6/photos' => 1,
            '/albums/7/photos' => 1,
            '/albums/8/photos' => 1,
            '/albums/9/photos' => 1,
            '/albums/10/photos' => 1,
        ]);
    }

    public function testHydrationPolicyDepth(): void
    {
        $provider = $this->App->get(AlbumProvider::class);
        $context = $provider
            ->getContext()
            ->withHydrationPolicy(HydrationPolicy::SUPPRESS)
            ->withHydrationPolicy(HydrationPolicy::EAGER, null, 1);

        $album = $provider
            ->with(Album::class, $context)
            ->hydrate(HydrationPolicy::EAGER, Task::class, 2)
            ->get(1);
        $this->assertIsArray($album->Photos);
        $this->assertCount(50, $album->Photos);
        $this->assertContainsOnlyInstancesOf(Photo::class, $album->Photos);
        $this->assertInstanceOf(User::class, $album->User);
        $this->assertNull($album->User->Posts);
        $this->assertNull($album->User->Albums);
        $this->assertIsArray($album->User->Tasks);
        $this->assertCount(20, $album->User->Tasks);
        $this->assertContainsOnlyInstancesOf(Task::class, $album->User->Tasks);

        $this->assertHttpRequestCounts([
            '/albums/1' => 1,
            '/albums/1/photos' => 1,
            '/users/1' => 1,
            '/users/1/todos' => 1,
        ]);
    }

    public function testHydrationPolicyEntity(): void
    {
        $provider = $this->App->get(AlbumProvider::class);
        $context = $provider
            ->getContext()
            ->withHydrationPolicy(HydrationPolicy::SUPPRESS)
            ->withHydrationPolicy(HydrationPolicy::EAGER, Task::class);

        $album = $provider->with(Album::class, $context)->get(1);
        $this->assertNull($album->Photos);
        $this->assertNull($album->User->Posts);
        $this->assertNull($album->User->Albums);
        $this->assertInstanceOf(User::class, $album->User);
        $this->assertIsArray($album->User->Tasks);
        $this->assertContainsOnlyInstancesOf(Task::class, $album->User->Tasks);
        $this->assertCount(20, $album->User->Tasks);

        foreach ($album->User->Tasks as $task) {
            $this->assertSame($album->User, $task->User);
        }

        $this->assertHttpRequestCounts([
            '/albums/1' => 1,
            '/users/1' => 1,
            '/users/1/todos' => 1,
        ]);
    }
}
