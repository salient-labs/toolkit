<?php declare(strict_types=1);

namespace Salient\Tests\Sync;

use Salient\Contract\Sync\Exception\SyncEntityNotFoundExceptionInterface;
use Salient\Contract\Sync\SyncEntityInterface;
use Salient\Contract\Sync\SyncProviderInterface;
use Salient\Sync\SyncSerializeRules as SerializeRules;
use Salient\Tests\Sync\Entity\Post;
use Salient\Tests\Sync\Entity\User;

/**
 * @covers \Salient\Sync\AbstractSyncEntity
 * @covers \Salient\Sync\SyncSerializeRules
 * @covers \Salient\Sync\SyncSerializeRulesBuilder
 */
final class AbstractSyncEntityTest extends SyncTestCase
{
    public function testDefaultProvider(): void
    {
        $this->assertSame($this->Provider, Post::getDefaultProvider($this->App));
    }

    public function testWithDefaultProvider(): void
    {
        $entityProvider = User::withDefaultProvider($this->App);
        $this->assertSame(User::class, $entityProvider->entity());
        $this->assertSame($this->Provider, $entityProvider->getProvider());
    }

    /**
     * @dataProvider idFromNameOrIdProvider
     *
     * @param int|string|false|null $expected
     * @param int|string|null $nameOrId
     * @param class-string<SyncEntityInterface> $entity
     */
    public function testIdFromNameOrId(
        $expected,
        ?float $expectedUncertainty,
        $nameOrId,
        string $entity,
        ?float $uncertaintyThreshold = null,
        ?string $nameProperty = null
    ): void {
        if ($expected === false) {
            $this->expectException(SyncEntityNotFoundExceptionInterface::class);
        }

        $uncertainty = -1.0;

        /** @var SyncProviderInterface */
        $provider = [$entity, 'getDefaultProvider']($this->App);
        $actual = [$entity, 'idFromNameOrId']($nameOrId, $provider, $uncertaintyThreshold, $nameProperty, $uncertainty);
        $this->assertSame($expected, $actual);
        $this->assertSame($expectedUncertainty, $uncertainty);
    }

    /**
     * @return array<array{int|string|false|null,float|null,int|string|null,string,float|null,string|null}>
     */
    public static function idFromNameOrIdProvider(): array
    {
        return [
            [
                null,
                null,
                null,
                User::class,
                0.6,
                'Name',
            ],
            [
                7,
                0.0,
                'weissnat',
                User::class,
                0.6,
                'Name',
            ],
            [
                7,
                0.0,
                7,
                User::class,
                0.6,
                'Name',
            ],
            [
                false,
                null,
                'clem',
                User::class,
                0.6,
                'Name',
            ],
        ];
    }

    public function testToArrayRecursionDetection(): void
    {
        $user = new User();
        $user->Id = 1;
        $user->Posts = [];

        $post = new Post();
        $post->Id = 101;
        $post->User = $user;
        $user->Posts[] = $post;

        $post = new Post();
        $post->Id = 102;
        $post->User = $user;
        $user->Posts[] = $post;

        $_user = $user->toArrayWith(
            SerializeRules::build()
                ->entity(User::class)
                ->sortByKey(true)
                ->build(),
            $this->Store,
        );
        $_post = $post->toArrayWith(
            SerializeRules::build()
                ->entity(Post::class)
                ->sortByKey(true)
                ->build(),
            $this->Store,
        );

        $this->assertSame([
            'address' => null,
            'albums' => null,
            'company' => null,
            'email' => null,
            'id' => 1,
            'name' => null,
            'phone' => null,
            'posts' => [
                [
                    'body' => null,
                    'comments' => null,
                    'id' => 101,
                    'title' => null,
                    'user' => [
                        '@type' => 'salient-tests:User',
                        '@id' => 1,
                        '@why' => 'Circular reference detected',
                    ],
                ],
                [
                    'body' => null,
                    'comments' => null,
                    'id' => 102,
                    'title' => null,
                    'user' => [
                        '@type' => 'salient-tests:User',
                        '@id' => 1,
                        '@why' => 'Circular reference detected',
                    ],
                ]
            ],
            'tasks' => null,
            'username' => null,
        ], $_user);
        $this->assertSame([
            'body' => null,
            'comments' => null,
            'id' => 102,
            'title' => null,
            'user' => [
                'address' => null,
                'albums' => null,
                'company' => null,
                'email' => null,
                'id' => 1,
                'name' => null,
                'phone' => null,
                'posts' => [
                    [
                        'body' => null,
                        'comments' => null,
                        'id' => 101,
                        'title' => null,
                        'user' => [
                            '@type' => 'salient-tests:User',
                            '@id' => 1,
                            '@why' => 'Circular reference detected',
                        ],
                    ],
                    [
                        '@type' => 'salient-tests:Post',
                        '@id' => 102,
                        '@why' => 'Circular reference detected',
                    ],
                ],
                'tasks' => null,
                'username' => null,
            ],
        ], $_post);
    }
}
