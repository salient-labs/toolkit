<?php declare(strict_types=1);

namespace Lkrms\Tests\Sync;

use Lkrms\Container\Application;
use Lkrms\Facade\File;
use Lkrms\Sync\Support\SyncStore;
use Lkrms\Tests\Sync\Provider\JsonPlaceholderApi;

abstract class SyncTestCase extends \Lkrms\Tests\TestCase
{
    protected ?string $BasePath;
    protected ?Application $App;
    protected ?SyncStore $Store;

    protected function setUp(): void
    {
        $this->BasePath = File::createTemporaryDirectory();

        $this->App =
            (new Application($this->BasePath))
                ->startCache()
                ->startSync(__METHOD__, [])
                ->syncNamespace(
                    'lkrms-tests',
                    'https://lkrms.github.io/php-util/tests/entity',
                    'Lkrms\Tests\Sync\Entity'
                )
                ->service(JsonPlaceholderApi::class);

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

        File::pruneDirectory($this->BasePath);
        rmdir($this->BasePath);

        $this->BasePath = null;
    }
}
