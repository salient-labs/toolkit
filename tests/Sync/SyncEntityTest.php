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
            'id'       => 1,
            'name'     => null,
            'username' => null,
            'email'    => null,
            'address'  => null,
            'phone'    => null,
            'company'  => null,
            'posts'    => [
                [
                    'id'                  => 101,
                    'title'               => null,
                    'body'                => null,
                    'meta_properties'     => [],
                    'meta_property_names' => [],
                    'user_id'             => 1
                ],
                [
                    'id'                  => 102,
                    'title'               => null,
                    'body'                => null,
                    'meta_properties'     => [],
                    'meta_property_names' => [],
                    'user_id'             => 1
                ]
            ],
            'meta_properties'     => [],
            'meta_property_names' => []
        ], $_user);
        $this->assertSame([
            'id'           => 102,
            'user'         => [
                'id'       => 1,
                'name'     => null,
                'username' => null,
                'email'    => null,
                'address'  => null,
                'phone'    => null,
                'company'  => null,
                'posts'    => [
                    [
                        'id'                  => 101,
                        'title'               => null,
                        'body'                => null,
                        'meta_properties'     => [],
                        'meta_property_names' => [],
                        'user_id'             => 1
                    ],
                    102
                ],
                'meta_properties'     => [],
                'meta_property_names' => []
            ],
            'title'               => null,
            'body'                => null,
            'meta_properties'     => [],
            'meta_property_names' => []
        ], $_post);
    }

}
