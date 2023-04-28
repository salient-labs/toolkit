<?php declare(strict_types=1);

namespace Lkrms\Tests\Sync\Support;

use Closure;
use Lkrms\Container\Container;
use Lkrms\Sync\Catalog\SyncOperation;
use Lkrms\Sync\Contract\ISyncClassResolver;
use Lkrms\Sync\Support\SyncIntrospector;
use Lkrms\Sync\Support\SyncStore;
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

    public function testProviderToEntity()
    {
        $this->assertEquals(
            User::class,
            SyncIntrospector::providerToEntity(UserProvider::class)
        );

        $this->assertEquals(
            'Component\Sync\Entity\People\Contact',
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
            new class implements ISyncClassResolver {
                public function entityToProvider(string $entity): string
                {
                    return preg_replace(
                        [
                            '/(?<=\\\\)Entity(?=\\\\)/i',
                            '/(?<=\\\\)([^\\\\]+)$/',
                            '/^\\\\+/',
                        ],
                        [
                            'Contract',
                            'Provides$1',
                            '',
                        ],
                        "\\$entity"
                    );;
                }

                public function providerToEntity(string $provider): ?string
                {
                    return preg_replace(
                        [
                            '/(?<=\\\\)Contract(?=\\\\)/i',
                            '/(?<=\\\\)Provides([^\\\\]+)$/',
                            '/^\\\\+/',
                        ],
                        [
                            'Entity',
                            '$1',
                            '',
                        ],
                        "\\$provider"
                    );
                }
            }
        );
    }

    public function testGetSyncOperationMethod()
    {
        $container = (new Container())->service(JsonPlaceholderApi::class);
        $provider = $container->get(PostProvider::class);

        $entityIntrospector = SyncIntrospector::get(Post::class);
        $providerIntrospector = SyncIntrospector::getService($container, PostProvider::class);

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
