<?php declare(strict_types=1);

namespace Lkrms\Tests\Sync;

use Lkrms\Container\Application;
use Lkrms\Sync\Support\SyncStore;
use Lkrms\Tests\Sync\Provider\JsonPlaceholderApi;
use Lkrms\Tests\TestCase;
use Lkrms\Utility\File;

abstract class SyncTestCase extends TestCase
{
    protected ?string $BasePath;
    protected ?Application $App;
    protected ?SyncStore $Store;

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
                    'lkrms-tests',
                    'https://lkrms.github.io/php-util/tests/entity',
                    'Lkrms\Tests\Sync\Entity'
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

        $this->App = null;
        $this->Store = null;

        File::pruneDir($this->BasePath);
        rmdir($this->BasePath);

        $this->BasePath = null;
    }
}