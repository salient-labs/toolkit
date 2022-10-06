<?php

declare(strict_types=1);

namespace Lkrms\Tests\Sync\Support;

use Closure;
use Lkrms\Container\Container;
use Lkrms\Sync\Support\SyncClosureBuilder;
use Lkrms\Sync\Support\SyncOperation;
use Lkrms\Tests\Sync\CustomEntity\Post;
use Lkrms\Tests\Sync\Entity\PostProvider;
use Lkrms\Tests\Sync\Provider\JsonPlaceholderApi;
use ReflectionFunction;

final class SyncClosureBuilderTest extends \Lkrms\Tests\TestCase
{
    public function testGetSyncOperationMethod()
    {
        $container = new Container();
        $container->service(JsonPlaceholderApi::class);
        $provider = $container->get(PostProvider::class);
        $entityClosureBuilder   = SyncClosureBuilder::get(Post::class);
        $providerClosureBuilder = SyncClosureBuilder::getBound($container, PostProvider::class);

        $this->assertEquals(null, $this->getMethodVar($providerClosureBuilder->getSyncOperationClosure(SyncOperation::READ, $entityClosureBuilder, $provider)));
        $this->assertEquals("getPosts", $this->getMethodVar($providerClosureBuilder->getSyncOperationClosure(SyncOperation::READ_LIST, $entityClosureBuilder, $provider)));
        $this->assertEquals(null, $this->getMethodVar($providerClosureBuilder->getSyncOperationClosure(SyncOperation::CREATE, $entityClosureBuilder, $provider)));
    }

    private function getMethodVar(?Closure $closure): ?string
    {
        if (!$closure)
        {
            return null;
        }

        return (new ReflectionFunction($closure))->getStaticVariables()["method"];
    }

}
