<?php declare(strict_types=1);

namespace Salient\Tests\Sync;

use Salient\Container\Application;
use Salient\Core\Utility\File;
use Salient\Sync\SyncStore;
use Salient\Tests\Sync\Provider\JsonPlaceholderApi;
use Salient\Tests\TestCase;

abstract class SyncTestCase extends TestCase
{
    protected string $BasePath;
    protected Application $App;
    protected SyncStore $Store;

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
        $this->BasePath = File::createTempDir();

        $this->App =
            (new Application($this->BasePath))
                ->startCache()
                ->startSync(__METHOD__, [])
                ->syncNamespace(
                    'salient-tests',
                    'https://salient-labs.github.io/toolkit/tests/entity',
                    'Salient\Tests\Sync\Entity'
                )
                ->provider(JsonPlaceholderApi::class);

        $this->Store = $this->App->get(SyncStore::class);
    }

    protected function tearDown(): void
    {
        $this
            ->App
            ->stopSync()
            ->unload();

        unset($this->App);
        unset($this->Store);

        File::deleteDir($this->BasePath, true);

        unset($this->BasePath);
    }
}
