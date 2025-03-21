<?php declare(strict_types=1);

namespace Salient\Tests\Sync\Entity\Provider;

use Salient\Contract\Sync\SyncContextInterface;
use Salient\Contract\Sync\SyncProviderInterface;
use Salient\Tests\Sync\Entity\Collides;

/**
 * Syncs Collides objects with a backend
 *
 * @method Collides createCollides(SyncContextInterface $ctx, Collides $collides)
 * @method Collides getCollides(SyncContextInterface $ctx, int|string|null $id)
 * @method Collides updateCollides(SyncContextInterface $ctx, Collides $collides)
 * @method Collides deleteCollides(SyncContextInterface $ctx, Collides $collides)
 * @method iterable<array-key,Collides> getCollideses(SyncContextInterface $ctx)
 *
 * @generated
 */
interface CollidesProvider extends SyncProviderInterface {}
