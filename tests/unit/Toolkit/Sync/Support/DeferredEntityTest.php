<?php declare(strict_types=1);

namespace Salient\Tests\Sync\Support;

use Salient\Contract\Sync\DeferralPolicy;
use Salient\Sync\Support\DeferredEntity;
use Salient\Tests\Sync\Entity\Provider\PostProvider;
use Salient\Tests\Sync\Entity\Post;
use Salient\Tests\Sync\Entity\User;
use Salient\Tests\Sync\Provider\JsonPlaceholderApi;
use Salient\Tests\Sync\SyncTestCase;

final class DeferredEntityTest extends SyncTestCase
{
    public function testDoNotResolve(): void
    {
        $provider = $this->App->get(PostProvider::class);
        $context = $provider
            ->getContext()
            ->withDeferralPolicy(DeferralPolicy::DO_NOT_RESOLVE);
        $postProvider = $provider->with(Post::class, $context);

        // Reading a property of a deferred entity should force it to resolve
        $post = $postProvider->get(1);
        $this->assertInstanceOf(DeferredEntity::class, $post->User);
        $this->assertSame('Leanne Graham', $post->User->Name);
        // @phpstan-ignore-next-line
        $this->assertInstanceOf(User::class, $post->User);

        // Same with __isset(), __set(), __unset()
        $post = $postProvider->get(11);
        $this->assertInstanceOf(DeferredEntity::class, $post->User);
        $this->assertTrue(isset($post->User->Email));
        // @phpstan-ignore-next-line
        $this->assertInstanceOf(User::class, $post->User);

        $post = $postProvider->get(21);
        $this->assertInstanceOf(DeferredEntity::class, $post->User);
        $post->User->Phone = null;
        // @phpstan-ignore-next-line
        $this->assertInstanceOf(User::class, $post->User);

        $post = $postProvider->get(31);
        $this->assertInstanceOf(DeferredEntity::class, $post->User);
        unset($post->User->Address);
        // @phpstan-ignore-next-line
        $this->assertInstanceOf(User::class, $post->User);
    }

    public function testResolveEarly(): void
    {
        $provider = $this->App->get(PostProvider::class);
        $context =
            $provider
                ->getContext()
                ->withDeferralPolicy(DeferralPolicy::RESOLVE_EARLY);

        /** @var JsonPlaceholderApi $provider */
        $data = $provider->getCurler('/posts/1')->get();
        $post = Post::provide($data, $provider, $context);
        $this->assertInstanceOf(User::class, $post->User);

        $userName = $post->User->Name;
        $this->assertSame('Leanne Graham', $userName);
    }

    public function testResolveLate(): void
    {
        $provider = $this->App->get(PostProvider::class);
        $context =
            $provider
                ->getContext()
                ->withDeferralPolicy(DeferralPolicy::RESOLVE_LATE);

        /** @var JsonPlaceholderApi $provider */
        $data = $provider->getCurler('/posts/1')->get();
        $post = Post::provide($data, $provider, $context);
        $this->assertInstanceOf(DeferredEntity::class, $post->User);

        $userName = $post->User->Name;
        $this->assertSame('Leanne Graham', $userName);
        // @phpstan-ignore-next-line
        $this->assertInstanceOf(User::class, $post->User);
    }

    public function testDeferList(): void
    {
        $provider = $this->App->get(PostProvider::class);
        $context =
            $provider
                ->getContext()
                ->withDeferralPolicy(DeferralPolicy::DO_NOT_RESOLVE);

        /** @var array<DeferredEntity<Post>>|Post[]|null */
        $list = null;
        DeferredEntity::deferList(
            $provider,
            $context,
            Post::class,
            [1, 2, 3],
            $list,
        );

        $this->assertIsArray($list);
        $this->assertCount(3, $list);
        $this->assertContainsOnlyInstancesOf(DeferredEntity::class, $list);

        $this->Store->resolveDeferredEntities();

        $this->assertIsArray($list);
        $this->assertCount(3, $list);
        $this->assertContainsOnlyInstancesOf(Post::class, $list);
    }
}
