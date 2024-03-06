<?php declare(strict_types=1);

namespace Salient\Tests\Sync\Entity\Provider;

use Salient\Contract\Sync\SyncContextInterface;
use Salient\Contract\Sync\SyncProviderInterface;
use Salient\Tests\Sync\Entity\Unimplemented;

/**
 * Syncs Unimplemented objects with a backend
 *
 * @method Unimplemented createUnimplemented(SyncContextInterface $ctx, Unimplemented $unimplemented)
 * @method Unimplemented getUnimplemented(SyncContextInterface $ctx, int|string|null $id)
 * @method Unimplemented updateUnimplemented(SyncContextInterface $ctx, Unimplemented $unimplemented)
 * @method Unimplemented deleteUnimplemented(SyncContextInterface $ctx, Unimplemented $unimplemented)
 * @method iterable<Unimplemented> getUnimplementeds(SyncContextInterface $ctx)
 *
 * @generated
 */
interface UnimplementedProvider extends SyncProviderInterface {}
