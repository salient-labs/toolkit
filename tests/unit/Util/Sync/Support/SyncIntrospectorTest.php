<?php declare(strict_types=1);

namespace Lkrms\Tests\Sync\Support;

use Lkrms\Sync\Catalog\SyncOperation;
use Lkrms\Sync\Support\SyncIntrospector;
use Lkrms\Sync\Support\SyncStore;
use Lkrms\Tests\Sync\Entity\Provider\TaskProvider;
use Lkrms\Tests\Sync\Entity\Provider\UserProvider;
use Lkrms\Tests\Sync\Entity\Task;
use Lkrms\Tests\Sync\Entity\User;
use Lkrms\Tests\Sync\Provider\JsonPlaceholderApi;
use Lkrms\Tests\Sync\SyncClassResolver;
use Salient\Container\Container;
use Salient\Tests\TestCase;
use Closure;
use ReflectionFunction;

final class SyncIntrospectorTest extends TestCase
{
    public function testEntityToProvider(): void
    {
        $this->assertEquals(
            UserProvider::class,
            SyncIntrospector::entityToProvider(User::class)
        );

        $this->assertEquals(
            'Component\Sync\Contract\People\ProvidesContact',
            SyncIntrospector::entityToProvider(
                // @phpstan-ignore-next-line
                'Component\Sync\Entity\People\Contact',
                $this->getStore()
            )
        );
    }

    public function testProviderToEntity(): void
    {
        $this->assertEquals(
            [User::class],
            SyncIntrospector::providerToEntity(UserProvider::class)
        );

        $this->assertEquals(
            ['Component\Sync\Entity\People\Contact'],
            SyncIntrospector::providerToEntity(
                // @phpstan-ignore-next-line
                'Component\Sync\Contract\People\ProvidesContact',
                $this->getStore()
            )
        );
    }

    private function getStore(): SyncStore
    {
        return (new SyncStore())->namespace(
            'component',
            'https://sync.lkrms.github.io/component',
            'Component\Sync',
            SyncClassResolver::class
        );
    }

    public function testGetSyncOperationMethod(): void
    {
        $container = (new Container())->provider(JsonPlaceholderApi::class);
        $provider = $container->get(TaskProvider::class);

        $entityIntrospector = SyncIntrospector::get(Task::class);
        $providerIntrospector = SyncIntrospector::getService($container, TaskProvider::class);

        $this->assertEquals('getTask', $this->getMethodVar($providerIntrospector->getDeclaredSyncOperationClosure(SyncOperation::READ, $entityIntrospector, $provider)));
        $this->assertEquals(null, $this->getMethodVar($providerIntrospector->getDeclaredSyncOperationClosure(SyncOperation::READ_LIST, $entityIntrospector, $provider)));
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
