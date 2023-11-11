<?php declare(strict_types=1);

namespace Lkrms\Tests\Sync\Support;

use Lkrms\Sync\Catalog\DeferralPolicy;
use Lkrms\Sync\Support\DeferredEntity;
use Lkrms\Tests\Sync\Entity\Provider\PostProvider;
use Lkrms\Tests\Sync\Entity\Post;
use Lkrms\Tests\Sync\Entity\User;
use Lkrms\Tests\Sync\Provider\JsonPlaceholderApi;

final class DeferredEntityTest extends \Lkrms\Tests\Sync\SyncTestCase
{
    public function testDoNotResolve(): void
    {
        $provider = $this->App->get(PostProvider::class);
        $context =
            $provider
                ->getContext()
                ->withDeferralPolicy(DeferralPolicy::DO_NOT_RESOLVE);

        $post = $provider->with(Post::class, $context)->get(1);
        $this->assertInstanceOf(DeferredEntity::class, $post->User);

        $userName = $post->User->Name;
        $this->assertSame('Leanne Graham', $userName);
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
