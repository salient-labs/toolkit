<?php

declare(strict_types=1);

namespace Lkrms\Tests\Sync;

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

        $_user = $user->toArray();
        $_post = $post->toArray();

        $this->assertSame([
            'address'      => null,
            'canonical_id' => null,
            'company'      => null,
            'email'        => null,
            'id'           => 1,
            'name'         => null,
            'phone'        => null,
            'posts'        => [
                [
                    'body'         => null,
                    'canonical_id' => null,
                    'id'      => 101,
                    'title'   => null,
                    'user_id' => 1
                ],
                [
                    'body'         => null,
                    'canonical_id' => null,
                    'id'      => 102,
                    'title'   => null,
                    'user_id' => 1
                ]
            ],
            'username' => null,
        ], $_user);
        $this->assertSame([
            'body'         => null,
            'canonical_id' => null,
            'id'               => 102,
            'title'            => null,
            'user'             => [
                'address'      => null,
                'canonical_id' => null,
                'company'      => null,
                'email'        => null,
                'id'           => 1,
                'name'         => null,
                'phone'        => null,
                'posts'        => [
                    [
                        'body'         => null,
                        'canonical_id' => null,
                        'id'      => 101,
                        'title'   => null,
                        'user_id' => 1
                    ],
                    102
                ],
                'username' => null,
            ],
        ], $_post);
    }

}
