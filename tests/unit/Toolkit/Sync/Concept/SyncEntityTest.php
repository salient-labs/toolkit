<?php declare(strict_types=1);

namespace Salient\Tests\Sync\Concept;

use Salient\Contract\Sync\SyncEntityProviderInterface;
use Salient\Contract\Sync\SyncProviderInterface;
use Salient\Sync\Exception\SyncEntityNotFoundException;
use Salient\Sync\SyncSerializeRulesBuilder as SerializeRulesBuilder;
use Salient\Tests\Sync\Entity\Post;
use Salient\Tests\Sync\Entity\User;
use Salient\Tests\Sync\Provider\JsonPlaceholderApi;
use Salient\Tests\Sync\SyncTestCase;

final class SyncEntityTest extends SyncTestCase
{
    public function testDefaultProvider(): void
    {
        $postProvider = Post::defaultProvider($this->App);
        $postEntityProvider = $postProvider->with(Post::class);
        $userEntityProvider = User::withDefaultProvider($this->App);

        $provider = $this->App->get(JsonPlaceholderApi::class);

        $this->assertSame($provider, $postProvider);
        $this->assertSame($provider, $postEntityProvider->getProvider());
        $this->assertSame($provider, $userEntityProvider->getProvider());
        $this->assertInstanceOf(SyncEntityProviderInterface::class, $postEntityProvider);
        $this->assertInstanceOf(SyncEntityProviderInterface::class, $userEntityProvider);
    }

    /**
     * @dataProvider idFromNameOrIdProvider
     *
     * @param int|string|false|null $expected
     * @param int|string|null $nameOrId
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
            $this->expectException(SyncEntityNotFoundException::class);
        }

        $uncertainty = -1.0;

        /** @var SyncProviderInterface */
        $provider = [$entity, 'defaultProvider']($this->App);
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

        $post = new Post();
        $post->Id = 101;
        $post->User = $user;
        $user->Posts[] = $post;

        $post = new Post();
        $post->Id = 102;
        $post->User = $user;
        $user->Posts[] = $post;

        $_user = $user->toArrayWith(
            SerializeRulesBuilder::build($this->App)
                ->entity(User::class)
                ->sortByKey(true)
                ->go()
        );
        $_post = $post->toArrayWith(
            SerializeRulesBuilder::build($this->App)
                ->entity(Post::class)
                ->sortByKey(true)
                ->go()
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
