<?php declare(strict_types=1);

namespace Lkrms\Tests\Sync\Support;

use Lkrms\Container\Container;
use Lkrms\Sync\Support\DeferredSyncEntity;
use Lkrms\Sync\Support\SyncContext;
use Lkrms\Tests\Sync\Entity\Post;
use Lkrms\Tests\Sync\Entity\Provider\PostProvider;
use Lkrms\Tests\Sync\Provider\JsonPlaceholderApi;

final class DeferredSyncEntityTest extends \Lkrms\Tests\TestCase
{
    public function testList()
    {
        $container = (new Container())->service(JsonPlaceholderApi::class);
        $provider  = $container->get(PostProvider::class);

        /**
         * @var DeferredSyncEntity[]
         */
        $list = null;
        DeferredSyncEntity::defer($provider, new SyncContext($container), Post::class, [1, 2, 3], $list);

        $this->assertIsArray($list);
        $this->assertCount(3, $list);
        $this->assertContainsOnlyInstancesOf(DeferredSyncEntity::class, $list);

        foreach ($list as $deferred) {
            $deferred->replace(Post::construct(['id' => $deferred->Deferred], $container));
        }

        $this->assertIsArray($list);
        $this->assertCount(3, $list);
        $this->assertContainsOnlyInstancesOf(Post::class, $list);
    }
}
