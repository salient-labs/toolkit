<?php declare(strict_types=1);

namespace Salient\Tests\Sync\Provider;

use Salient\Contract\Core\DateFormatterInterface;
use Salient\Core\Utility\Get;
use Salient\Core\DateFormatter;
use Salient\Sync\HttpSyncProvider;
use Salient\Tests\Sync\Entity\Provider\UserProvider;

/**
 * @method User createUser(SyncContextInterface $ctx, User $user)
 * @method User getUser(SyncContextInterface $ctx, int|string|null $id)
 * @method User updateUser(SyncContextInterface $ctx, User $user)
 * @method User deleteUser(SyncContextInterface $ctx, User $user)
 * @method iterable<User> getUsers(SyncContextInterface $ctx)
 */
class MockProvider extends HttpSyncProvider implements UserProvider
{
    public function name(): string
    {
        return Get::basename(__CLASS__);
    }

    public function getBackendIdentifier(): array
    {
        return [__CLASS__];
    }

    protected function getBaseUrl(?string $path): string
    {
        return 'http://localhost';
    }

    protected function getDateFormatter(?string $path = null): DateFormatterInterface
    {
        return new DateFormatter();
    }
}
