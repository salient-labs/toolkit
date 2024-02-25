<?php declare(strict_types=1);

namespace Lkrms\Tests\Sync\Command;

use Lkrms\Tests\Sync\Provider\JsonPlaceholderApi;
use Salient\Cli\Contract\CliApplicationInterface;
use Salient\Cli\Contract\CliCommandInterface;
use Salient\Sync\Command\CheckSyncProviderHeartbeat;
use Salient\Tests\CommandTestCase;

class CheckSyncProviderHeartbeatTest extends CommandTestCase
{
    protected function startApp(CliApplicationInterface $app): CliApplicationInterface
    {
        return $app
            ->startCache()
            ->startSync(__METHOD__, [])
            ->provider(JsonPlaceholderApi::class);
    }

    protected function makeCommandAssertions(
        CliApplicationInterface $app,
        CliCommandInterface $command,
        ...$args
    ): void {
        $httpRequestCount = $args[8] ?? null;

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
        $output = str_replace(
            ["\r" . \PHP_EOL, \PHP_EOL],
            ["\r", "\n"],
            $output
        );
        $this->assertCommandProduces(
            $output,
            $exitStatus,
            CheckSyncProviderHeartbeat::class,
            $args,
            [],
            true,
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
                ],
                2,
            ],
        ];
    }
}
