<?php declare(strict_types=1);

namespace Salient\Tests\Sync\Support;

use Salient\Container\Container;
use Salient\Contract\Container\ContainerInterface;
use Salient\Contract\Sync\SyncOperation;
use Salient\Contract\Sync\SyncStoreInterface;
use Salient\Sync\Reflection\SyncEntityReflection;
use Salient\Sync\Reflection\SyncProviderReflection;
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
 * @covers \Salient\Sync\Reflection\SyncEntityReflection
 * @covers \Salient\Sync\Reflection\SyncProviderReflection
 * @covers \Salient\Sync\SyncUtil
 */
final class SyncIntrospectorTest extends TestCase
{
    public function testGetEntityProvider(): void
    {
        $this->assertSame(
            UserProvider::class,
            SyncUtil::getEntityTypeProvider(User::class)
        );

        $this->assertSame(
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
        $this->assertSame(
            [User::class],
            SyncUtil::getProviderEntityTypes(UserProvider::class)
        );

        $this->assertSame(
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

        $_entity = new SyncEntityReflection(Task::class);
        $_provider = new SyncProviderReflection($container->getClass(TaskProvider::class));

        $this->assertSame('gettask', $this->getMethodVar($_provider->getSyncOperationClosure(SyncOperation::READ, $_entity, $provider)));
        $this->assertNull($this->getMethodVar($_provider->getSyncOperationClosure(SyncOperation::READ_LIST, $_entity, $provider)));
        $this->assertNull($this->getMethodVar($_provider->getSyncOperationClosure(SyncOperation::CREATE, $_entity, $provider)));
    }

    private function getMethodVar(?Closure $closure): ?string
    {
        if (!$closure) {
            return null;
        }

        return (new ReflectionFunction($closure))->getStaticVariables()['method'];
    }
}
