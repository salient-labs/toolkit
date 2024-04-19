<?php declare(strict_types=1);

namespace Salient\Tests\Sync\Command;

use Salient\Sync\Command\CheckSyncProviderHeartbeat;

/**
 * @covers \Salient\Sync\Command\CheckSyncProviderHeartbeat
 * @covers \Salient\Sync\Command\AbstractSyncCommand
 */
final class CheckSyncProviderHeartbeatTest extends SyncCommandTestCase
{
    /**
     * @dataProvider runProvider
     *
     * @param string[] $args
     * @param array<string,int>|null $httpRequestCount
     */
    public function testRun(
        string $output,
        int $exitStatus,
        array $args,
        ?array $httpRequestCount = null,
        int $runs = 1
    ): void {
        $this->assertCommandProduces(
            static::normaliseConsoleOutput($output),
            $exitStatus,
            CheckSyncProviderHeartbeat::class,
            $args,
            [],
            true,
            false,
            null,
            $runs,
            $httpRequestCount === null
                ? null
                : static function ($app) use ($httpRequestCount): void {
                    static::assertSameHttpRequests($httpRequestCount, $app);
                },
        );
    }

    /**
     * @return array<array{string,int,string[],3?:array<string,int>|null,4?:int}>
     */
    public static function runProvider(): array
    {
        $cr = "\r";

        return [
            [
                <<<EOF
                ==> Sending heartbeat request to 1 provider
                 -> Checking JSONPlaceholder { http://localhost:3001 } [#1]{$cr}
                ==> Connected to JSONPlaceholder { http://localhost:3001 } as Leanne Graham
                 -> Heartbeat OK: JSONPlaceholder { http://localhost:3001 } [#1]
                 // 1 provider checked without errors
                ==> Sending heartbeat request to 1 provider
                 -> Checking JSONPlaceholder { http://localhost:3001 } [#1]{$cr}
                 -> Heartbeat OK: JSONPlaceholder { http://localhost:3001 } [#1]
                 // 1 provider checked without errors

                EOF,
                0,
                [],
                [
                    'http://localhost:3001/users/1' => 1,
                ],
                2,
            ],
        ];
    }
}
