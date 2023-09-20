<?php declare(strict_types=1);

namespace Lkrms\Tests\Sync\CustomEntity;

use Lkrms\Tests\Sync\Entity\Post;

class User extends \Lkrms\Tests\Sync\Entity\User
{
    public function postLoad(): void
    {
        if ($this->Posts === null &&
                $this->Id !== null &&
                ($provider = $this->provider())) {
            $this->Posts = $provider->with(Post::class)->getListA(['user' => $this->Id]);
        }
    }
}
