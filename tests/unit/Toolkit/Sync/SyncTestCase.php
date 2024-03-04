<?php declare(strict_types=1);

namespace Salient\Tests\Sync;

use Salient\Container\Container;
use Salient\Sync\SyncStore;
use Salient\Tests\Sync\Provider\JsonPlaceholderApi;
use Salient\Tests\TestCase;

abstract class SyncTestCase extends TestCase
{
    protected Container $App;
    protected SyncStore $Store;
    protected JsonPlaceholderApi $Provider;

    /**
     * @param array<string,int> $expected
     */
    protected function assertHttpRequestCounts(array $expected): void
    {
        $provider = $this->App->get(JsonPlaceholderApi::class);
        $baseUrl = $provider->getBaseUrl();
        foreach ($expected as $endpoint => $count) {
            $endpoints[$baseUrl . $endpoint] = $count;
        }
        $this->assertSame($endpoints ?? [], $provider->HttpRequestCount);
    }

    protected function setUp(): void
    {
        $this->App = (new Container())
            ->provider(JsonPlaceholderApi::class);

        $this->Store = $this
            ->App
            ->singleton(SyncStore::class)
            ->get(SyncStore::class)
            ->namespace(
                'salient-tests',
                'https://salient-labs.github.io/toolkit/tests/entity',
                'Salient\Tests\Sync\Entity',
            );

        $this->Provider = $this->App->get(JsonPlaceholderApi::class);
    }

    protected function tearDown(): void
    {
        $this
            ->App
            ->unload();
        unset($this->App);
        unset($this->Store);
    }
}
