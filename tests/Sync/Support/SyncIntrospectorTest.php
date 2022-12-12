<?php declare(strict_types=1);

namespace Lkrms\Tests\Sync\Support;

use Closure;
use Lkrms\Container\Container;
use Lkrms\Sync\Support\SyncIntrospector;
use Lkrms\Sync\Support\SyncOperation;
use Lkrms\Tests\Sync\CustomEntity\Post;
use Lkrms\Tests\Sync\Entity\Provider\PostProvider;
use Lkrms\Tests\Sync\Entity\Provider\UserProvider;
use Lkrms\Tests\Sync\Entity\User;
use Lkrms\Tests\Sync\Provider\JsonPlaceholderApi;
use ReflectionFunction;

final class SyncIntrospectorTest extends \Lkrms\Tests\TestCase
{
    public function testEntityToProvider()
    {
        $this->assertEquals(UserProvider::class, SyncIntrospector::entityToProvider(User::class));
    }

    public function testProviderToEntity()
    {
        $this->assertEquals(User::class, SyncIntrospector::providerToEntity(UserProvider::class));
    }

    public function testGetSyncOperationMethod()
    {
        $container = (new Container())->service(JsonPlaceholderApi::class);
        $provider  = $container->get(PostProvider::class);

        $entityIntrospector   = SyncIntrospector::get(Post::class);
        $providerIntrospector = SyncIntrospector::getBound($container, PostProvider::class);

        $this->assertEquals(null, $this->getMethodVar($providerIntrospector->getDeclaredSyncOperationClosure(SyncOperation::READ, $entityIntrospector, $provider)));
        $this->assertEquals('getPosts', $this->getMethodVar($providerIntrospector->getDeclaredSyncOperationClosure(SyncOperation::READ_LIST, $entityIntrospector, $provider)));
        $this->assertEquals(null, $this->getMethodVar($providerIntrospector->getDeclaredSyncOperationClosure(SyncOperation::CREATE, $entityIntrospector, $provider)));
    }

    private function getMethodVar(?Closure $closure): ?string
    {
        if (!$closure) {
            return null;
        }

        return (new ReflectionFunction($closure))->getStaticVariables()['method'];
    }
}
