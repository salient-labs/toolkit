<?php declare(strict_types=1);

namespace Lkrms\Tests\Sync\Command;

use Lkrms\Cli\CliApplication;
use Lkrms\Sync\Command\CheckSyncProviderHeartbeat;
use Lkrms\Tests\Sync\Provider\JsonPlaceholderApi;

class CheckSyncProviderHeartbeatTest extends \Lkrms\Tests\CommandTestCase
{
    protected function startApp(CliApplication $app): CliApplication
    {
        return $app
            ->startSync(__METHOD__, [])
            ->service(JsonPlaceholderApi::class);
    }

    /**
     * @dataProvider runProvider
     *
     * @param string[] $args
     */
    public function testRun(string $output, int $exitStatus, array $args): void
    {
        $this->assertCommandProduces($output, $exitStatus, CheckSyncProviderHeartbeat::class, $args);
    }

    /**
     * @return array<array{string,int,string[]}>
     */
    public static function runProvider(): array
    {
        $cr = "\r";

        return [
            [
                <<<EOF
                ==> Sending heartbeat request to 1 provider
                 -> Checking JSONPlaceholder { http://localhost:3001 } [#1]{$cr}
                 -> Heartbeat check not supported: JSONPlaceholder { http://localhost:3001 } [#1]
                 // 1 provider checked without errors

                EOF,
                0,
                []
            ],
        ];
    }
}
