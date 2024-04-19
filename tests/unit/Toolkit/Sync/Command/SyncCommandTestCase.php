<?php declare(strict_types=1);

namespace Salient\Tests\Sync\Command;

use Salient\Contract\Cli\CliApplicationInterface;
use Salient\Tests\Sync\Provider\JsonPlaceholderApi;
use Salient\Tests\CommandTestCase;

abstract class SyncCommandTestCase extends CommandTestCase
{
    /**
     * @param array<string,int> $requests
     */
    public static function assertSameHttpRequests(
        array $requests,
        CliApplicationInterface $app
    ): void {
        static::assertSame(
            $requests,
            $app->get(JsonPlaceholderApi::class)->HttpRequests,
            'JsonPlaceholderApi::$HttpRequests',
        );
    }

    protected function setUpApp(CliApplicationInterface $app): CliApplicationInterface
    {
        return $app
            ->startCache()
            ->startSync(static::class, [])
            ->provider(JsonPlaceholderApi::class);
    }
}
