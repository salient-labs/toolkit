<?php

namespace Salient\Tests\Sync\Support;

use Salient\Contract\Sync\SyncEntityInterface;
use Salient\Contract\Sync\SyncOperation;
use Salient\Sync\Support\SyncPipelineArgument;
use Salient\Tests\Sync\Entity\User;
use Salient\Tests\Sync\SyncTestCase;
use InvalidArgumentException;

/**
 * @covers \Salient\Sync\Support\SyncPipelineArgument
 */
final class SyncPipelineArgumentTest extends SyncTestCase
{
    /**
     * @dataProvider constructorProvider
     *
     * @param SyncOperation::* $operation
     * @param mixed[] $args
     * @param int|string|null $id
     */
    public function testConstructor(
        ?string $exception,
        int $operation,
        array $args = [],
        $id = null,
        ?SyncEntityInterface $entity = null
    ): void {
        $ctx = $this->Provider->getContext();
        $this->maybeExpectException($exception);
        $argument = new SyncPipelineArgument($operation, $ctx, $args, $id, $entity);
        $this->assertSame($operation, $argument->Operation);
        $this->assertSame($ctx, $argument->Context);
        $this->assertSame($args, $argument->Args);
        if ($operation === SyncOperation::READ) {
            $this->assertSame($id, $argument->Id);
            return;
        }
        $this->assertNull($argument->Id);
        if ($entity !== null) {
            $this->assertSame($entity, $argument->Entity);
        }
    }

    /**
     * @return array<array{string|null,SyncOperation::*,2?:mixed[],3?:int|string|null,4?:SyncEntityInterface|null}>
     */
    public static function constructorProvider(): array
    {
        return [
            [
                null,
                SyncOperation::READ,
            ],
            [
                null,
                SyncOperation::READ,
                [],
                1,
            ],
            [
                null,
                SyncOperation::READ,
                [],
                'foo',
            ],
            [
                null,
                SyncOperation::READ_LIST,
            ],
            [
                null,
                SyncOperation::READ_LIST,
                [['name' => 'foo']],
            ],
            [
                null,
                SyncOperation::CREATE,
                [],
                null,
                new User(),
            ],
            [
                null,
                SyncOperation::CREATE_LIST,
            ],
            [
                InvalidArgumentException::class . ',$entity required for SyncOperation::CREATE',
                SyncOperation::CREATE,
            ],
        ];
    }

    public function testEntityPassedByRef(): void
    {
        $ctx = $this->Provider->getContext();
        $entity1 = new User();
        $entity2 = new User();

        $entity = $entity1;
        $argument = new SyncPipelineArgument(SyncOperation::CREATE, $ctx, [], null, $entity);
        $this->assertSame($entity1, $argument->Entity);
        $entity = $entity2;
        $this->assertSame($entity1, $argument->Entity);
        unset($entity);

        $entity = $entity1;
        $argument = new SyncPipelineArgument(SyncOperation::CREATE_LIST, $ctx, [], null, $entity);
        $this->assertSame($entity1, $argument->Entity);
        $entity = $entity2;
        $this->assertNotSame($entity1, $argument->Entity);
        $this->assertSame($entity2, $argument->Entity);
    }
}
