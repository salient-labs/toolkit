<?php declare(strict_types=1);

namespace Salient\Tests\Sync\Command;

use Salient\Contract\Sync\SyncProviderInterface;
use Salient\Sync\Command\CheckSyncProviderHeartbeat;
use Salient\Tests\Sync\External\Provider\MockProvider as ExternalMockProvider;
use Salient\Tests\Sync\Provider\JsonPlaceholderApi;
use Salient\Tests\Sync\Provider\MockProvider;
use stdClass;

/**
 * @covers \Salient\Sync\Command\CheckSyncProviderHeartbeat
 * @covers \Salient\Sync\Command\AbstractSyncCommand
 */
final class CheckSyncProviderHeartbeatTest extends SyncCommandTestCase
{
    /**
     * @dataProvider runProvider
     * @backupGlobals enabled
     *
     * @param string[] $args
     * @param array<string,int>|null $httpRequestCount
     * @param class-string[] $providers
     */
    public function testRun(
        string $output,
        int $exitStatus,
        array $args,
        ?array $httpRequestCount = null,
        int $runs = 1,
        array $providers = [],
        bool $providerless = false
    ): void {
        $this->Providers = $providers;
        $this->Providerless = $providerless;
        $this->assertCommandProduces(
            self::normaliseConsoleOutput($output),
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
                    self::assertSameHttpRequests($httpRequestCount, $app);
                },
        );
    }

    /**
     * @return array<array{string,int,string[],3?:array<string,int>|null,4?:int,5?:class-string[],6?:bool}>
     */
    public static function runProvider(): array
    {
        return [
            [
                <<<EOF
> Sending heartbeat request to 1 provider
Connected to JSONPlaceholder { http://localhost:3001 } as Leanne Graham
- Heartbeat OK: JSONPlaceholder { http://localhost:3001 } [#1]
✔ 1 provider checked without errors
> Sending heartbeat request to 1 provider
- Heartbeat OK: JSONPlaceholder { http://localhost:3001 } [#1]
✔ 1 provider checked without errors

EOF,
                0,
                [],
                [
                    'http://localhost:3001/users/1' => 1,
                ],
                2,
            ],
            [
                <<<EOF
> Sending heartbeat request to 2 providers
Connected to JSONPlaceholder { http://localhost:3001 } as Leanne Graham
- Heartbeat OK: JSONPlaceholder { http://localhost:3001 } [#1]
- Heartbeat check not supported: MockProvider [#2]
✔ 2 providers checked without errors

EOF,
                0,
                [],
                [
                    'http://localhost:3001/users/1' => 1,
                ],
                1,
                [
                    stdClass::class,
                    MockProvider::class,
                ],
            ],
            [
                <<<EOF
NAME
    app - Send a heartbeat request to registered providers

SYNOPSIS
    app [-fH] [-t seconds] [--] [provider...]

DESCRIPTION
    If no providers are given, all providers are checked.

    If a heartbeat request fails, app continues to the next provider unless
    -f/--fail-early is given, in which case it exits immediately.

    The command exits with a non-zero status if a provider backend is
    unreachable.

OPTIONS
    provider...
        The provider can be:

        - json-placeholder-api
        - ALL

        The default provider is: ALL

    -t, --ttl seconds
        The lifetime of a positive result, in seconds

        The default seconds is: 300

    -f, --fail-early
        If a check fails, exit without checking other providers

    -H, --har
        Record HTTP requests to an HTTP Archive file in the log directory

EOF,
                0,
                ['--help'],
                [],
            ],
            [
                <<<EOF
NAME
    app - Send a heartbeat request to registered providers

SYNOPSIS
    app [-fH] [-t seconds] [--] [provider...]

DESCRIPTION
    If no providers are given, all providers are checked.

    If a heartbeat request fails, app continues to the next provider unless
    -f/--fail-early is given, in which case it exits immediately.

    The command exits with a non-zero status if a provider backend is
    unreachable.

OPTIONS
    provider...
        The provider can be:

        - Salient\Tests\Sync\External\Provider\MockProvider
        - Salient\Tests\Sync\Provider\MockProvider
        - json-placeholder-api
        - ALL

        The default provider is: ALL

    -t, --ttl seconds
        The lifetime of a positive result, in seconds

        The default seconds is: 300

    -f, --fail-early
        If a check fails, exit without checking other providers

    -H, --har
        Record HTTP requests to an HTTP Archive file in the log directory

EOF,
                0,
                ['--help'],
                [],
                1,
                [
                    stdClass::class,
                    MockProvider::class,
                    ExternalMockProvider::class,
                ],
            ],
            [
                <<<EOF
NAME
    app - Send a heartbeat request to one or more providers

SYNOPSIS
    app [-fH] [-t seconds] [--] provider...

DESCRIPTION
    If a heartbeat request fails, app continues to the next provider unless
    -f/--fail-early is given, in which case it exits immediately.

    The command exits with a non-zero status if a provider backend is
    unreachable.

OPTIONS
    provider...
        The fully-qualified name of the provider to check

    -t, --ttl seconds
        The lifetime of a positive result, in seconds

        The default seconds is: 300

    -f, --fail-early
        If a check fails, exit without checking other providers

    -H, --har
        Record HTTP requests to an HTTP Archive file in the log directory

EOF,
                0,
                ['--help'],
                [],
                1,
                [],
                true,
            ],
            [
                <<<EOF
> Sending heartbeat request to 1 provider
Connected to JSONPlaceholder { http://localhost:3001 } as Leanne Graham
- Heartbeat OK: JSONPlaceholder { http://localhost:3001 } [#1]
✔ 1 provider checked without errors

EOF,
                0,
                [JsonPlaceholderApi::class],
                [
                    'http://localhost:3001/users/1' => 1,
                ],
                1,
                [],
                true,
            ],
            [
                sprintf(<<<EOF
Error: stdClass does not implement %s

app [-fH] [-t <seconds>] [--] <provider>...

See 'app --help' for more information.

EOF, SyncProviderInterface::class),
                1,
                [stdClass::class],
                [],
                1,
                [],
                true,
            ],
        ];
    }
}
