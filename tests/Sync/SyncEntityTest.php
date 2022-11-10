<?php

declare(strict_types=1);

namespace Lkrms\Tests\Sync;

use Lkrms\Sync\Support\SyncSerializeRulesBuilder as SerializeRulesBuilder;
use Lkrms\Tests\Sync\Entity\Post;
use Lkrms\Tests\Sync\Entity\User;

final class SyncEntityTest extends \Lkrms\Tests\TestCase
{
    public function testToArrayRecursionDetection()
    {
        $user     = new User();
        $user->Id = 1;

        $post          = new Post();
        $post->Id      = 101;
        $post->User    = $user;
        $user->Posts[] = $post;

        $post          = new Post();
        $post->Id      = 102;
        $post->User    = $user;
        $user->Posts[] = $post;

        $_user = $user->toCustomArray(
            SerializeRulesBuilder::entity(User::class)->sort(true)->go()
        );
        $_post = $post->toCustomArray(
            SerializeRulesBuilder::entity(Post::class)->sort(true)->go()
        );

        $this->assertSame([
            'address' => null,
            'company' => null,
            'email'   => null,
            'id'      => 1,
            'name'    => null,
            'phone'   => null,
            'posts'   => [
                [
                    'body'      => null,
                    'id'        => 101,
                    'title'     => null,
                    'user'      => [
                        '@type' => '/Lkrms/Tests/Sync/Entity/User',
                        '@id'   => 1,
                        '@why'  => 'Circular reference detected',
                    ],
                ],
                [
                    'body'      => null,
                    'id'        => 102,
                    'title'     => null,
                    'user'      => [
                        '@type' => '/Lkrms/Tests/Sync/Entity/User',
                        '@id'   => 1,
                        '@why'  => 'Circular reference detected',
                    ],
                ]
            ],
            'username' => null,
        ], $_user);
        $this->assertSame([
            'body'        => null,
            'id'          => 102,
            'title'       => null,
            'user'        => [
                'address' => null,
                'company' => null,
                'email'   => null,
                'id'      => 1,
                'name'    => null,
                'phone'   => null,
                'posts'   => [
                    [
                        'body'      => null,
                        'id'        => 101,
                        'title'     => null,
                        'user'      => [
                            '@type' => '/Lkrms/Tests/Sync/Entity/User',
                            '@id'   => 1,
                            '@why'  => 'Circular reference detected',
                        ],
                    ],
                    [
                        '@type' => '/Lkrms/Tests/Sync/Entity/Post',
                        '@id'   => 102,
                        '@why'  => 'Circular reference detected',
                    ],
                ],
                'username' => null,
            ],
        ], $_post);
    }

}
