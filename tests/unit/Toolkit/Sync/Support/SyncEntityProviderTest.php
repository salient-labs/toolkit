<?php declare(strict_types=1);

namespace Salient\Tests\Sync\Support;

use Salient\Tests\Sync\CustomEntity\Task as CustomTask;
use Salient\Tests\Sync\CustomEntity\Unserviced as CustomUnserviced;
use Salient\Tests\Sync\Entity\Post;
use Salient\Tests\Sync\Entity\Unimplemented;
use Salient\Tests\Sync\Entity\User;
use Salient\Tests\Sync\Provider\MockProvider;
use Salient\Tests\Sync\SyncTestCase;
use LogicException;

/**
 * @covers \Salient\Sync\Support\SyncEntityProvider
 */
final class SyncEntityProviderTest extends SyncTestCase
{
    public function testGetList(): void
    {
        $posts = $this
            ->Provider
            ->with(Post::class)
            ->doNotResolve()
            ->doNotHydrate()
            ->getListA();

        $userProvider = $this
            ->Provider
            ->with(User::class)
            ->doNotResolve()
            ->doNotHydrate();

        // Load every user entity so deferred users are resolved
        // immediately
        $userProvider->getListA();

        $this->assertSameHttpEndpointRequests($requests = [
            '/posts' => 1,
            '/users' => 1,
        ]);

        $flattened = [];
        foreach ($posts as $post) {
            if (is_int($post->Id) && $post->Id % 9) {
                continue;
            }
            $user = $userProvider->get($post->User->Id);
            $flattened[] = [
                'id' => $post->Id,
                'title' => $post->Title,
                'userId' => $user->Id,
                'userName' => $user->Name,
                'userEmail' => $user->Email,
            ];
        }

        $this->assertSameHttpEndpointRequests($requests);

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

    public function testWithUnservicedEntity(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage(sprintf(
            '%s does not service %s',
            get_class($this->Provider),
            CustomUnserviced::class,
        ));
        $this->Provider->with(CustomUnserviced::class);
    }

    public function testWithUnimplementedEntity(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage(sprintf(
            '%s does not service %s',
            get_class($this->Provider),
            Unimplemented::class,
        ));
        $this->Provider->with(Unimplemented::class);
    }

    public function testWithUnboundEntity(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage(sprintf(
            '%s does not service %s',
            get_class($this->Provider),
            CustomTask::class,
        ));
        $this->Provider->with(CustomTask::class);
    }

    public function testWithInvalidContext(): void
    {
        $context = $this->App->get(MockProvider::class)->getContext();
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage(sprintf(
            'Context has a different provider (MockProvider, expected %s)',
            $this->Provider->getName(),
        ));
        $this->Provider->with(User::class, $context);
    }
}
