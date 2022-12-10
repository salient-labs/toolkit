<?php declare(strict_types=1);

namespace Lkrms\Tests\Sync\CustomEntity;

use Lkrms\Tests\Sync\Entity\Post;

class User extends \Lkrms\Tests\Sync\Entity\User
{
    public function _setId($value)
    {
        /** @var iterable<Post> */
        $posts = Post::backend()->getList(['user' => $value]);

        $this->Id    = $value;
        $this->Posts = iterator_to_array($posts);
    }
}
