<?php declare(strict_types=1);

namespace Salient\Tests\Sync;

use PHPUnit\Framework\MockObject\MockObject;
use Salient\Contract\Sync\SyncProviderInterface;
use Salient\Tests\Sync\CustomEntity\User as CustomUser;
use Salient\Tests\Sync\Entity\Provider\PostProvider;
use Salient\Tests\Sync\Entity\Post;
use Salient\Tests\Sync\Entity\User;
use Salient\Tests\Sync\Provider\JsonPlaceholderApi;
use Salient\Utility\Get;
use Salient\Utility\Regex;
use LogicException;
use Stringable;

/**
 * @covers \Salient\Sync\SyncStore
 * @covers \Salient\Core\Store
 */
final class SyncStoreTest extends SyncTestCase
{
    public function testRun(): void
    {
        $store = $this->Store;
        $this->assertFalse($store->runHasStarted());
        $this->triggerRun();
        $this->assertTrue($store->runHasStarted());
        $this->assertSame(1, $store->getRunId());
        $this->assertMatchesRegularExpression(Regex::delimit('^' . Regex::UUID . '$', '/'), $uuid = $store->getRunUuid());
        $this->assertSame(Get::binaryUuid($uuid), $store->getBinaryRunUuid());
        $this->assertSame('salient-tests:User', $store->getEntityTypeUri(User::class));
        $this->assertSame('https://salient-labs.github.io/toolkit/tests/entity/User', $store->getEntityTypeUri(User::class, false));
        $this->assertSame('/Salient/Tests/Sync/CustomEntity/User', $store->getEntityTypeUri(CustomUser::class));
        $this->assertSame('/Salient/Tests/Sync/CustomEntity/User', $store->getEntityTypeUri(CustomUser::class, false));
    }

    public function testRunHasNotStarted(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Run has not started');
        $this->Store->getRunId();
    }

    public function testProviderAlreadyRegistered(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage(sprintf('Provider already registered: %s', JsonPlaceholderApi::class));
        $this->Store->registerProvider($this->Provider);
        $this->triggerRun();
    }

    public function testHasProvider(): void
    {
        $store = $this->Store;
        $signature = $store->getProviderSignature($this->Provider);
        $mockProvider = $this->getMockProvider();
        $mockSignature = $store->getProviderSignature($mockProvider);
        $this->assertTrue($store->hasProvider($this->Provider));
        $this->assertTrue($store->hasProvider($signature));
        $this->assertFalse($store->hasProvider($mockProvider));
        $this->assertFalse($store->hasProvider($mockSignature));
        $this->triggerRun();
        $this->assertTrue($store->hasProvider($this->Provider));
        $this->assertTrue($store->hasProvider($signature));
        $this->assertFalse($store->hasProvider($mockProvider));
        $this->assertFalse($store->hasProvider($mockSignature));
        $store->registerProvider($mockProvider);
        $this->assertTrue($store->hasProvider($mockProvider));
        $this->assertTrue($store->hasProvider($mockSignature));
    }

    public function testGetProviderId(): void
    {
        $store = $this->Store;
        $signature = $store->getProviderSignature($this->Provider);
        $mockProvider = $this->getMockProvider();
        $mockSignature = $store->getProviderSignature($mockProvider);
        $this->assertSame(1, $store->getProviderId($this->Provider));
        $this->assertSame(1, $store->getProviderId($signature));
        $this->assertCallbackThrowsException(fn() => $store->getProviderId($mockProvider), LogicException::class, 'Provider not registered: ');
        $this->assertCallbackThrowsException(fn() => $store->getProviderId($mockSignature), LogicException::class, 'Provider not registered');
        $store->registerProvider($mockProvider);
        $this->assertSame(2, $store->getProviderId($mockProvider));
        $this->assertSame(2, $store->getProviderId($mockSignature));
    }

    public function testGetProvider(): void
    {
        $store = $this->Store;
        $signature = $store->getProviderSignature($this->Provider);
        $mockProvider = $this->getMockProvider([1]);
        $mockSignature = $store->getProviderSignature($mockProvider);
        $this->assertSame($this->Provider, $store->getProvider($signature));
        $this->assertCallbackThrowsException(fn() => $store->getProvider(1), LogicException::class, 'Provider ID not issued during run: 1');
        $this->assertCallbackThrowsException(fn() => $store->getProvider($mockSignature), LogicException::class, 'Provider not registered');
        $store->registerProvider($mockProvider);
        $this->assertSame($mockProvider, $store->getProvider($mockSignature));
        $this->triggerRun();
        $this->assertSame($this->Provider, $store->getProvider($signature));
        $this->assertSame($this->Provider, $store->getProvider(1));
        $this->assertSame($mockProvider, $store->getProvider($mockSignature));
        $this->assertSame($mockProvider, $store->getProvider(2));
        $this->assertCallbackThrowsException(fn() => $store->getProvider(3), LogicException::class, 'Provider not registered: #3');
        $this->assertCallbackThrowsException(fn() => $store->getProvider($store->getProviderSignature($this->getMockProvider([2]))), LogicException::class, 'Provider not registered');
    }

    private function triggerRun(): void
    {
        // Trigger the start of a run
        $this->App->get(PostProvider::class)->with(Post::class)->get(1);
    }

    /**
     * @param array<int|float|string|bool|Stringable|null> $identifier
     * @return SyncProviderInterface&MockObject
     */
    private function getMockProvider(array $identifier = [])
    {
        /** @var SyncProviderInterface&MockObject */
        $mockProvider = $this->createMock(SyncProviderInterface::class);
        $mockProvider
            ->method('getBackendIdentifier')
            ->willReturn($identifier);
        return $mockProvider;
    }
}
