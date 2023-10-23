<?php declare(strict_types=1);

namespace Lkrms\Tests\Sync\Command;

use Lkrms\Cli\Contract\ICliCommand;
use Lkrms\Cli\CliApplication;
use Lkrms\Sync\Command\CheckSyncProviderHeartbeat;
use Lkrms\Tests\Sync\Provider\JsonPlaceholderApi;

class CheckSyncProviderHeartbeatTest extends \Lkrms\Tests\CommandTestCase
{
    protected function startApp(CliApplication $app): CliApplication
    {
        return $app
            ->startCache()
            ->startSync(__METHOD__, [])
            ->service(JsonPlaceholderApi::class);
    }

    protected function makeCommandAssertions(
        CliApplication $app,
        ICliCommand $command,
        ...$args
    ): void {
        $httpRequestCount = $args[6] ?? null;

        if ($httpRequestCount === null) {
            return;
        }

        $provider = $app->get(JsonPlaceholderApi::class);
        $this->assertSame(
            $httpRequestCount,
            $provider->HttpRequestCount,
            'JsonPlaceholderApi::$HttpRequestCount',
        );
    }

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
            $output,
            $exitStatus,
            CheckSyncProviderHeartbeat::class,
            $args,
            null,
            $runs,
            $httpRequestCount,
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
                    'http://localhost:3001/users/1/posts' => 1,
                ],
                2,
            ],
        ];
    }
}
