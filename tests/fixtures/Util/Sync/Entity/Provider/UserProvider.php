<?php declare(strict_types=1);

namespace Lkrms\Tests\Sync\Entity\Provider;

use Lkrms\Tests\Sync\Entity\User;
use Salient\Sync\Contract\ISyncContext;
use Salient\Sync\Contract\ISyncProvider;

/**
 * Syncs User objects with a backend
 *
 * @method User createUser(ISyncContext $ctx, User $user)
 * @method User getUser(ISyncContext $ctx, int|string|null $id)
 * @method User updateUser(ISyncContext $ctx, User $user)
 * @method User deleteUser(ISyncContext $ctx, User $user)
 * @method iterable<User> getUsers(ISyncContext $ctx)
 *
 * @generated
 */
interface UserProvider extends ISyncProvider {}
