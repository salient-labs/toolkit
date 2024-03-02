<?php declare(strict_types=1);

namespace Salient\Tests\Sync\Support;

use Salient\Contract\Sync\SyncContextInterface;
use Salient\Contract\Sync\SyncOperation;
use Salient\Tests\Sync\Provider\JsonPlaceholderApi;
use Salient\Tests\Sync\SyncTestCase;

final class SyncContextTest extends SyncTestCase
{
    public function testOffline(): void
    {
        $context = $this->getContext();

        $this->assertNull($context->getOffline());
        $this->assertSame($context, $context->offlineFirst());
        $this->assertNull($context->getOffline());

        $this->assertNotSame($context, $context2 = $context->online());
        $this->assertFalse($context2->getOffline());
        $this->assertSame($context2, $context2->online());
        $this->assertFalse($context2->getOffline());

        $this->assertNotSame($context2, $context3 = $context2->offline());
        $this->assertTrue($context3->getOffline());
        $this->assertSame($context3, $context3->offline());
        $this->assertTrue($context3->getOffline());
    }

    public function testWithArgsWithoutArgs(): void
    {
        $context = $this->getContext();

        $this->assertSame($context, $context->withFilter(SyncOperation::READ_LIST));
        $this->assertSame($context, $context->withFilter(SyncOperation::READ, null));
    }

    /**
     * @dataProvider withArgsProvider
     *
     * @param array<string,mixed>|string $expected
     * @param mixed ...$args
     */
    public function testWithArgs($expected, ...$args): void
    {
        $context = $this->getContext();
        $context2 = $context->withFilter(SyncOperation::READ_LIST, ...$args);

        $this->assertNotSame($context, $context2);
        $this->assertSame($expected, $context2->getFilter());
    }

    /**
     * @return array<array{array<string,mixed>|string,...}>
     */
    public function withArgsProvider(): array
    {
        return [
            [
                ['org_unit' => [42, 71], '$orderBy' => 'Name'],
                ['OrgUnit' => [42, 71], '$orderBy' => 'Name'],
            ],
        ];
    }

    private function getContext(): SyncContextInterface
    {
        return $this->App->get(JsonPlaceholderApi::class)->getContext();
    }
}
