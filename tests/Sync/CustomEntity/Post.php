<?php

declare(strict_types=1);

namespace Lkrms\Tests\Sync\CustomEntity;

use Lkrms\Tests\Sync\Entity\User;

class Post extends \Lkrms\Tests\Sync\Entity\Post
{
    public function _setUserId($value)
    {
        $this->User = User::from(["id" => $value]);
    }
}

