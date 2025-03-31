<?php declare(strict_types=1);

namespace Salient\Tests\Sync\Command;

use Salient\Sync\Command\SendHttpSyncProviderRequest;
use Salient\Sync\Http\HttpSyncProvider;
use Salient\Tests\Sync\External\Provider\MockProvider as ExternalMockProvider;
use Salient\Tests\Sync\Provider\MockProvider;
use stdClass;

/**
 * @covers \Salient\Sync\Command\SendHttpSyncProviderRequest
 * @covers \Salient\Sync\Command\AbstractSyncCommand
 */
final class SendHttpSyncProviderRequestTest extends SyncCommandTestCase
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
        string $name,
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
            SendHttpSyncProviderRequest::class,
            $args,
            [$name],
            true,
            false,
            null,
            $runs,
            $httpRequestCount === null
                ? null
                : static function ($app) use ($httpRequestCount): void {
                    self::assertSameHttpRequests($httpRequestCount, $app);
                },
            true,
        );
    }

    /**
     * @return array<array{string,int,string,string[],4?:array<string,int>|null,5?:int,6?:class-string[],7?:bool}>
     */
    public static function runProvider(): array
    {
        return [
            [
                <<<EOF
NAME
    app get - Send a GET request to an HTTP provider

SYNOPSIS
    app get [-PsH] [-q field=value,...] [--] provider endpoint

OPTIONS
    provider
        The provider can be:

        - json-placeholder-api

    endpoint
        The endpoint to request, e.g. '/posts'

    -q, --query field=value,...
        A query parameter to apply to the request

    -P, --paginate
        Use pagination to iterate over the response

    -s, --stream
        Output a stream of entities when pagination is used

    -H, --har
        Record HTTP requests to an HTTP Archive file in the log directory

EOF,
                0,
                'get',
                ['get', '--help'],
                [],
            ],
            [
                <<<EOF
NAME
    app patch - Send a PATCH request to an HTTP provider

SYNOPSIS
    app patch [-H] [-q field=value,...] [-J file] [--] provider endpoint

OPTIONS
    provider
        The provider can be:

        - json-placeholder-api

    endpoint
        The endpoint to request, e.g. '/posts'

    -q, --query field=value,...
        A query parameter to apply to the request

    -J, --data file
        The path to JSON-serialized data to submit with the request

    -H, --har
        Record HTTP requests to an HTTP Archive file in the log directory

EOF,
                0,
                'patch',
                ['patch', '--help'],
                [],
            ],
            [
                <<<EOF
NAME
    app post - Send a POST request to an HTTP provider

SYNOPSIS
    app post [-PsH] [-q field=value,...] [-J file] [--] provider endpoint

OPTIONS
    provider
        The provider can be:

        - Salient\Tests\Sync\External\Provider\MockProvider
        - Salient\Tests\Sync\Provider\MockProvider
        - json-placeholder-api

    endpoint
        The endpoint to request, e.g. '/posts'

    -q, --query field=value,...
        A query parameter to apply to the request

    -J, --data file
        The path to JSON-serialized data to submit with the request

    -P, --paginate
        Use pagination to iterate over the response

    -s, --stream
        Output a stream of entities when pagination is used

    -H, --har
        Record HTTP requests to an HTTP Archive file in the log directory

EOF,
                0,
                'post',
                ['post', '--help'],
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
    app head - Send a HEAD request to an HTTP provider

SYNOPSIS
    app head [-H] [-q field=value,...] [--] provider endpoint

OPTIONS
    provider
        The fully-qualified name of the HTTP provider to use

    endpoint
        The endpoint to request, e.g. '/posts'

    -q, --query field=value,...
        A query parameter to apply to the request

    -H, --har
        Record HTTP requests to an HTTP Archive file in the log directory

EOF,
                0,
                'head',
                ['head', '--help'],
                [],
                1,
                [],
                true,
            ],
            [
                sprintf(<<<EOF
Error: stdClass does not inherit %s

app get [-PsH] [-q <field=value>,...] [--] <provider> <endpoint>

See 'app help get' for more information.

EOF, HttpSyncProvider::class),
                1,
                'get',
                ['get', stdClass::class, '/'],
                [],
                1,
                [],
                true,
            ],
            [
                <<<EOF
Error: invalid query (Invalid key[=value] pair: '=value')

app get [-PsH] [-q <field=value>,...] [--] <provider> <endpoint>

See 'app help get' for more information.

EOF,
                1,
                'get',
                ['get', '--query', '=value', 'json-placeholder-api', '/'],
                [],
            ],
            [
                <<<EOF
Error: MockProvider does not support pagination

app get [-PsH] [-q <field=value>,...] [--] <provider> <endpoint>

See 'app help get' for more information.

EOF,
                1,
                'get',
                ['get', '--paginate', 'mock', '/'],
                [],
                1,
                [
                    MockProvider::class,
                ],
            ],
        ];
    }
}
