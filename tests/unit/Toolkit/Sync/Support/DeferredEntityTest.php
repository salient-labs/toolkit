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
        $context =
            $provider
                ->getContext()
                ->withDeferralPolicy(DeferralPolicy::DO_NOT_RESOLVE);

        $post = $provider->with(Post::class, $context)->get(1);
        $this->assertInstanceOf(DeferredEntity::class, $post->User);

        // Reading a property of a deferred entity should force it to resolve
        $userName = $post->User->Name;
        $this->assertSame('Leanne Graham', $userName);
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
