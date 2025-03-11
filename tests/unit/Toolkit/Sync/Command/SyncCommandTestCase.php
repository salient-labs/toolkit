<?php declare(strict_types=1);

namespace Salient\Tests\Sync\Command;

use Salient\Cli\CliApplication;
use Salient\Tests\Sync\Entity\Provider\CommentProvider;
use Salient\Tests\Sync\Provider\JsonPlaceholderApi;
use Salient\Tests\CommandTestCase;

abstract class SyncCommandTestCase extends CommandTestCase
{
    /** @var class-string[] */
    protected array $Providers = [];
    protected bool $Providerless = false;

    /**
     * @param array<string,int> $requests
     */
    public static function assertSameHttpRequests(
        array $requests,
        CliApplication $app
    ): void {
        self::assertSame(
            $requests,
            $app->get(JsonPlaceholderApi::class)->HttpRequests,
            'JsonPlaceholderApi::$HttpRequests',
        );
    }

    protected function setUpApp(CliApplication $app): CliApplication
    {
        if (!$this->Providerless) {
            foreach ($this->Providers as $provider) {
                $app = $app->provider($provider);
            }
            $app = $app->provider(JsonPlaceholderApi::class, null, [CommentProvider::class]);
        }

        return $app
            ->startCache()
            ->startSync(static::class, []);
    }

    protected function setUp(): void
    {
        $this->Providers = [];
        $this->Providerless = false;
    }
}
