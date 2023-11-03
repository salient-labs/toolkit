<?php declare(strict_types=1);

namespace Lkrms\Tests\Sync\Support;

use Lkrms\Sync\Catalog\HydrationFlag;
use Lkrms\Sync\Support\DeferredRelationship;
use Lkrms\Tests\Sync\Entity\Provider\UserProvider;
use Lkrms\Tests\Sync\Entity\Post;
use Lkrms\Tests\Sync\Entity\User;

final class DeferredRelationshipTest extends \Lkrms\Tests\Sync\SyncTestCase
{
    public function testLazyHydration(): void
    {
        $provider = $this->App->get(UserProvider::class);
        $context = $provider->getContext()->withHydrationFlags(
            HydrationFlag::LAZY | HydrationFlag::NO_FILTER
        );

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
        // @phpstan-ignore-next-line
        $this->assertSame('sunt aut facere repellat provident occaecati excepturi optio reprehenderit', $post->Title);
    }

    public function testEagerHydration(): void
    {
        $provider = $this->App->get(UserProvider::class);
        $context = $provider->getContext()->withHydrationFlags(
            HydrationFlag::EAGER | HydrationFlag::NO_FILTER
        );

        $user = $provider->with(User::class, $context)->get(1);
        $this->assertIsArray($user->Posts);
        $this->assertCount(10, $user->Posts);
        $this->assertContainsOnlyInstancesOf(Post::class, $user->Posts);
        $this->assertSame($user, $user->Posts[0]->User);
        $this->assertSame('sunt aut facere repellat provident occaecati excepturi optio reprehenderit', $user->Posts[0]->Title);
    }
}
