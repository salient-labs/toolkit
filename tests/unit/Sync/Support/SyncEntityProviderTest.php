<?php declare(strict_types=1);

namespace Lkrms\Tests\Sync\Support;

use Lkrms\Sync\Catalog\HydrationPolicy;
use Lkrms\Tests\Sync\Entity\Post;
use Lkrms\Tests\Sync\Entity\User;
use Lkrms\Tests\Sync\Provider\JsonPlaceholderApi;

final class SyncEntityProviderTest extends \Lkrms\Tests\Sync\SyncTestCase
{
    public function testGetListA(): void
    {
        $postEntityProvider = Post::withDefaultProvider($this->App)
            ->doNotResolve()
            ->doNotHydrate();
        $posts = $postEntityProvider->getListA();

        $userEntityProvider = User::withDefaultProvider($this->App)
            ->doNotResolve()
            ->doNotHydrate();
        // Load every user entity so deferred users are resolved
        // immediately
        $userEntityProvider->getListA();

        $provider = $postEntityProvider->provider();
        if ($provider instanceof JsonPlaceholderApi) {
            $before = $provider->HttpRequestCount;
        }

        $flattened = [];
        foreach ($posts as $post) {
            if ($post->Id % 9) {
                continue;
            }
            $user = $userEntityProvider->get($post->User->Id);
            $flattened[] = [
                'id' => $post->Id,
                'title' => $post->Title,
                'userId' => $user->Id,
                'userName' => $user->Name,
                'userEmail' => $user->Email,
            ];
        }

        if ($provider instanceof JsonPlaceholderApi) {
            $this->assertSame(
                $before,
                $provider->HttpRequestCount,
                'JsonPlaceholderApi::$HttpRequestCount',
            );
            $this->assertCount(
                2,
                $provider->HttpRequestCount,
                'JsonPlaceholderApi::$HttpRequestCount',
            );
        }

        $this->assertSame([
            [
                'id' => 9,
                'title' => 'nesciunt iure omnis dolorem tempora et accusantium',
                'userId' => 1,
                'userName' => 'Leanne Graham',
                'userEmail' => 'Sincere@april.biz',
            ],
            [
                'id' => 18,
                'title' => 'voluptate et itaque vero tempora molestiae',
                'userId' => 2,
                'userName' => 'Ervin Howell',
                'userEmail' => 'Shanna@melissa.tv',
            ],
            [
                'id' => 27,
                'title' => 'quasi id et eos tenetur aut quo autem',
                'userId' => 3,
                'userName' => 'Clementine Bauch',
                'userEmail' => 'Nathan@yesenia.net',
            ],
            [
                'id' => 36,
                'title' => 'fuga nam accusamus voluptas reiciendis itaque',
                'userId' => 4,
                'userName' => 'Patricia Lebsack',
                'userEmail' => 'Julianne.OConner@kory.org',
            ],
            [
                'id' => 45,
                'title' => 'ut numquam possimus omnis eius suscipit laudantium iure',
                'userId' => 5,
                'userName' => 'Chelsey Dietrich',
                'userEmail' => 'Lucio_Hettinger@annie.ca',
            ],
            [
                'id' => 54,
                'title' => 'sit asperiores ipsam eveniet odio non quia',
                'userId' => 6,
                'userName' => 'Mrs. Dennis Schulist',
                'userEmail' => 'Karley_Dach@jasper.info',
            ],
            [
                'id' => 63,
                'title' => 'voluptas blanditiis repellendus animi ducimus error sapiente et suscipit',
                'userId' => 7,
                'userName' => 'Kurtis Weissnat',
                'userEmail' => 'Telly.Hoeger@billy.biz',
            ],
            [
                'id' => 72,
                'title' => 'sint hic doloribus consequatur eos non id',
                'userId' => 8,
                'userName' => 'Nicholas Runolfsdottir V',
                'userEmail' => 'Sherwood@rosamond.me',
            ],
            [
                'id' => 81,
                'title' => 'tempora rem veritatis voluptas quo dolores vero',
                'userId' => 9,
                'userName' => 'Glenna Reichert',
                'userEmail' => 'Chaim_McDermott@dana.io',
            ],
            [
                'id' => 90,
                'title' => 'ad iusto omnis odit dolor voluptatibus',
                'userId' => 9,
                'userName' => 'Glenna Reichert',
                'userEmail' => 'Chaim_McDermott@dana.io',
            ],
            [
                'id' => 99,
                'title' => 'temporibus sit alias delectus eligendi possimus magni',
                'userId' => 10,
                'userName' => 'Clementina DuBuque',
                'userEmail' => 'Rey.Padberg@karina.biz',
            ],
        ], $flattened);
    }
}
