<?php declare(strict_types=1);

namespace Salient\Tests\Sync\Provider;

use Salient\Contract\Container\SingletonInterface;
use Salient\Core\DateFormatter;
use Salient\Sync\HttpSyncProvider;
use Salient\Tests\Sync\Entity\Provider\UserProvider;
use Salient\Tests\Sync\Entity\User;
use Salient\Tests\Sync\External\Entity\Provider\CollidesProvider;
use Salient\Tests\Sync\External\Entity\Collides;
use Salient\Utility\Get;

/**
 * @method User createUser(SyncContextInterface $ctx, User $user)
 * @method User getUser(SyncContextInterface $ctx, int|string|null $id)
 * @method User updateUser(SyncContextInterface $ctx, User $user)
 * @method User deleteUser(SyncContextInterface $ctx, User $user)
 * @method iterable<User> getUsers(SyncContextInterface $ctx)
 * @method Collides createCollides(SyncContextInterface $ctx, Collides $collides)
 * @method Collides getCollides(SyncContextInterface $ctx, int|string|null $id)
 * @method Collides updateCollides(SyncContextInterface $ctx, Collides $collides)
 * @method Collides deleteCollides(SyncContextInterface $ctx, Collides $collides)
 * @method iterable<Collides> getCollideses(SyncContextInterface $ctx)
 */
class MockProvider extends HttpSyncProvider implements
    SingletonInterface,
    UserProvider,
    CollidesProvider
{
    public function getName(): string
    {
        return Get::basename(static::class);
    }

    public function getBackendIdentifier(): array
    {
        return [static::class];
    }

    protected function getBaseUrl(string $path): string
    {
        return 'http://localhost';
    }

    protected function createDateFormatter(): DateFormatter
    {
        return new DateFormatter();
    }
}
