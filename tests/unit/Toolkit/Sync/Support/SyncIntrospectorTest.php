<?php declare(strict_types=1);

namespace Salient\Tests\Sync\Support;

use Salient\Container\Container;
use Salient\Contract\Container\ContainerInterface;
use Salient\Contract\Sync\SyncOperation;
use Salient\Contract\Sync\SyncStoreInterface;
use Salient\Sync\Support\SyncIntrospector;
use Salient\Sync\SyncUtil;
use Salient\Tests\Sync\Entity\Provider\TaskProvider;
use Salient\Tests\Sync\Entity\Provider\UserProvider;
use Salient\Tests\Sync\Entity\Task;
use Salient\Tests\Sync\Entity\User;
use Salient\Tests\Sync\Provider\JsonPlaceholderApi;
use Salient\Tests\Sync\SyncNamespaceHelper;
use Salient\Tests\TestCase;
use Closure;
use ReflectionFunction;

/**
 * @covers \Salient\Sync\Support\SyncIntrospector
 * @covers \Salient\Sync\SyncUtil
 */
final class SyncIntrospectorTest extends TestCase
{
    public function testGetEntityProvider(): void
    {
        $this->assertEquals(
            UserProvider::class,
            SyncUtil::getEntityTypeProvider(User::class)
        );

        $this->assertEquals(
            'Component\Sync\Contract\People\ProvidesContact',
            SyncUtil::getEntityTypeProvider(
                // @phpstan-ignore argument.type
                'Component\Sync\Entity\People\Contact',
                $this->getContainer()->get(SyncStoreInterface::class)
            )
        );
    }

    public function testGetProviderEntities(): void
    {
        $this->assertEquals(
            [User::class],
            SyncUtil::getProviderEntityTypes(UserProvider::class)
        );

        $this->assertEquals(
            ['Component\Sync\Entity\People\Contact'],
            SyncUtil::getProviderEntityTypes(
                // @phpstan-ignore argument.type
                'Component\Sync\Contract\People\ProvidesContact',
                $this->getContainer()->get(SyncStoreInterface::class)
            )
        );
    }

    private function getContainer(): ContainerInterface
    {
        ($container = new Container())
            ->get(SyncStoreInterface::class)
            ->registerNamespace(
                'component',
                'https://sync.salient-labs.github.io/component',
                'Component\Sync',
                new SyncNamespaceHelper(),
            );

        return $container;
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
