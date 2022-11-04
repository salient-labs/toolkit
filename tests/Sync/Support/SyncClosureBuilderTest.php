<?php

declare(strict_types=1);

namespace Lkrms\Tests\Sync\Support;

use Closure;
use Lkrms\Container\Container;
use Lkrms\Sync\Support\SyncClosureBuilder;
use Lkrms\Sync\Support\SyncOperation;
use Lkrms\Tests\Sync\CustomEntity\Post;
use Lkrms\Tests\Sync\Entity\Provider\PostProvider;
use Lkrms\Tests\Sync\Entity\Provider\UserProvider;
use Lkrms\Tests\Sync\Entity\User;
use Lkrms\Tests\Sync\Provider\JsonPlaceholderApi;
use ReflectionFunction;

final class SyncClosureBuilderTest extends \Lkrms\Tests\TestCase
{
    public function testEntityToProvider()
    {
        $this->assertEquals(UserProvider::class, SyncClosureBuilder::entityToProvider(User::class));
    }

    public function testProviderToEntity()
    {
        $this->assertEquals(User::class, SyncClosureBuilder::providerToEntity(UserProvider::class));
    }

    public function testGetSyncOperationMethod()
    {
        $container = ((new Container())
            ->service(JsonPlaceholderApi::class));
        $provider = $container->get(PostProvider::class);

        $entityClosureBuilder   = SyncClosureBuilder::get(Post::class);
        $providerClosureBuilder = SyncClosureBuilder::getBound($container, PostProvider::class);

        $this->assertEquals(null, $this->getMethodVar($providerClosureBuilder->getDeclaredSyncOperationClosure(SyncOperation::READ, $entityClosureBuilder, $provider)));
        $this->assertEquals("getPosts", $this->getMethodVar($providerClosureBuilder->getDeclaredSyncOperationClosure(SyncOperation::READ_LIST, $entityClosureBuilder, $provider)));
        $this->assertEquals(null, $this->getMethodVar($providerClosureBuilder->getDeclaredSyncOperationClosure(SyncOperation::CREATE, $entityClosureBuilder, $provider)));
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
