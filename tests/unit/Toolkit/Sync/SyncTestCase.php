<?php declare(strict_types=1);

namespace Salient\Tests\Sync;

use Salient\Container\Container;
use Salient\Contract\Sync\SyncStoreInterface;
use Salient\Tests\Sync\Provider\JsonPlaceholderApi;
use Salient\Tests\TestCase;

abstract class SyncTestCase extends TestCase
{
    protected Container $App;
    protected SyncStoreInterface $Store;
    protected JsonPlaceholderApi $Provider;

    /**
     * Assert that the provider has made the given HTTP requests
     *
     * @param array<string,int> $expected An array that maps endpoints to
     * request counts.
     */
    public function assertSameHttpEndpointRequests(array $expected): void
    {
        $baseUrl = $this->Provider->getBaseUrl();
        foreach ($expected as $endpoint => $count) {
            $endpoints[$baseUrl . $endpoint] = $count;
        }

        $this->assertEqualsCanonicalizing(
            $endpoints ?? [],
            $this->Provider->HttpRequests
        );
    }

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        $this->App = (new Container())
            ->provider(JsonPlaceholderApi::class);

        $this->Store = $this
            ->App
            ->get(SyncStoreInterface::class)
            ->registerNamespace(
                'salient-tests',
                'https://salient-labs.github.io/toolkit/tests/entity',
                'Salient\Tests\Sync\Entity',
            );

        $this->Provider = $this->App->get(JsonPlaceholderApi::class);
    }

    /**
     * @inheritDoc
     */
    protected function tearDown(): void
    {
        $this
            ->App
            ->unload();
        unset($this->App);
        unset($this->Store);
        unset($this->Provider);
    }
}
