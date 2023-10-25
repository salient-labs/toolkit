<?php declare(strict_types=1);

namespace Lkrms\Tests\Sync\Concept;

use Lkrms\Sync\Contract\ISyncEntityProvider;
use Lkrms\Sync\Contract\ISyncProvider;
use Lkrms\Sync\Exception\SyncEntityNotFoundException;
use Lkrms\Sync\Support\SyncSerializeRulesBuilder as SerializeRulesBuilder;
use Lkrms\Tests\Sync\Entity\Post;
use Lkrms\Tests\Sync\Entity\User;
use Lkrms\Tests\Sync\Provider\JsonPlaceholderApi;

final class SyncEntityTest extends \Lkrms\Tests\Sync\SyncTestCase
{
    public function testDefaultProvider(): void
    {
        $postProvider = Post::defaultProvider($this->App);
        $postEntityProvider = $postProvider->with(Post::class);
        $userEntityProvider = User::withDefaultProvider($this->App);

        $provider = $this->App->get(JsonPlaceholderApi::class);

        $this->assertSame($provider, $postProvider);
        $this->assertSame($provider, $postEntityProvider->provider());
        $this->assertSame($provider, $userEntityProvider->provider());
        $this->assertInstanceOf(ISyncEntityProvider::class, $postEntityProvider);
        $this->assertInstanceOf(ISyncEntityProvider::class, $userEntityProvider);
    }

    /**
     * @dataProvider idFromNameOrIdProvider
     *
     * @param int|string|false|null $expected
     * @param int|string|null $nameOrId
     * @param string $entity
     */
    public function testIdFromNameOrId(
        $expected,
        ?float $expectedUncertainty,
        $nameOrId,
        string $entity,
        ?float $uncertaintyThreshold = 0.5,
        ?string $nameProperty = null
    ): void {
        if ($expected === false) {
            $this->expectException(SyncEntityNotFoundException::class);
        }

        $uncertainty = -1;

        /** @var ISyncProvider */
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
            'company' => null,
            'email' => null,
            'id' => 1,
            'name' => null,
            'phone' => null,
            'posts' => [
                [
                    'body' => null,
                    'id' => 101,
                    'title' => null,
                    'user' => [
                        '@type' => 'lkrms-tests:User',
                        '@id' => 1,
                        '@why' => 'Circular reference detected',
                    ],
                ],
                [
                    'body' => null,
                    'id' => 102,
                    'title' => null,
                    'user' => [
                        '@type' => 'lkrms-tests:User',
                        '@id' => 1,
                        '@why' => 'Circular reference detected',
                    ],
                ]
            ],
            'username' => null,
        ], $_user);
        $this->assertSame([
            'body' => null,
            'id' => 102,
            'title' => null,
            'user' => [
                'address' => null,
                'company' => null,
                'email' => null,
                'id' => 1,
                'name' => null,
                'phone' => null,
                'posts' => [
                    [
                        'body' => null,
                        'id' => 101,
                        'title' => null,
                        'user' => [
                            '@type' => 'lkrms-tests:User',
                            '@id' => 1,
                            '@why' => 'Circular reference detected',
                        ],
                    ],
                    [
                        '@type' => 'lkrms-tests:Post',
                        '@id' => 102,
                        '@why' => 'Circular reference detected',
                    ],
                ],
                'username' => null,
            ],
        ], $_post);
    }
}
