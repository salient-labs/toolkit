<?php declare(strict_types=1);

namespace Lkrms\Tests\Sync\Support;

use Lkrms\Sync\Catalog\SyncEntityHydrationFlag as HydrationFlag;
use Lkrms\Sync\Support\DeferredSyncEntity;
use Lkrms\Tests\Sync\Entity\Provider\PostProvider;
use Lkrms\Tests\Sync\Entity\Post;
use Lkrms\Tests\Sync\Entity\User;

final class DeferredSyncEntityTest extends \Lkrms\Tests\Sync\SyncTestCase
{
    public function testDeferLazy(): void
    {
        $provider = $this->App->get(PostProvider::class);
        $context = $provider->getContext()->withHydrationFlags(
            HydrationFlag::LAZY | HydrationFlag::NO_FILTER
        );

        $post = $provider->with(Post::class, $context)->get(1);
        $this->assertInstanceOf(DeferredSyncEntity::class, $post->User);

        $userName = $post->User->Name;
        $this->assertSame('Leanne Graham', $userName);
        $this->assertInstanceOf(User::class, $post->User);
    }

    public function testDeferEager(): void
    {
        $provider = $this->App->get(PostProvider::class);
        $context = $provider->getContext()->withHydrationFlags(
            HydrationFlag::EAGER | HydrationFlag::NO_FILTER
        );

        $post = $provider->with(Post::class, $context)->get(1);
        $this->assertInstanceOf(User::class, $post->User);

        $userName = $post->User->Name;
        $this->assertSame('Leanne Graham', $userName);
    }

    public function testDeferList(): void
    {
        $provider = $this->App->get(PostProvider::class);
        $context = $provider->getContext()->withHydrationFlags(
            HydrationFlag::DEFER | HydrationFlag::NO_FILTER
        );

        /**
         * @var array<DeferredSyncEntity<Post>>|Post[]|null
         */
        $list = null;
        DeferredSyncEntity::deferList(
            $provider,
            $context,
            Post::class,
            [1, 2, 3],
            $list,
        );

        $this->assertIsArray($list);
        $this->assertCount(3, $list);
        $this->assertContainsOnlyInstancesOf(DeferredSyncEntity::class, $list);

        $this->Store->resolveDeferredEntities();

        $this->assertIsArray($list);
        $this->assertCount(3, $list);
        $this->assertContainsOnlyInstancesOf(Post::class, $list);
    }
}
