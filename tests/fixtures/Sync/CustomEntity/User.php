<?php declare(strict_types=1);

namespace Lkrms\Tests\Sync\CustomEntity;

use Lkrms\Tests\Sync\Entity\Post;

class User extends \Lkrms\Tests\Sync\Entity\User
{
    public function postLoad(): void
    {
        if ($this->Id === null || $this->Posts !== null) {
            return;
        }

        $provider = $this->provider();
        if (!$provider) {
            return;
        }

        $this->Posts =
            $provider->with(
                Post::class,
                $this->context()->push($this)
            )->getListA();
    }
}
