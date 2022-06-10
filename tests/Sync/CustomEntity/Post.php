<?php

declare(strict_types=1);

namespace Lkrms\Tests\Sync\CustomEntity;

use Lkrms\Tests\Sync\Entity\User;

class Post extends \Lkrms\Tests\Sync\Entity\Post
{
    /**
     * @var User[]
     */
    private static $_users = [];

    public function _setUserId($value)
    {
        if (array_key_exists($value, self::$_users))
        {
            $this->User = & self::$_users[$value];
            return;
        }

        self::$_users[$value] = null;
        /** @var User */
        $user = User::backend()->get($value);
        self::$_users[$value] = $user;

        $this->User = & self::$_users[$value];
    }
}
