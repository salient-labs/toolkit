<?php declare(strict_types=1);

namespace Salient\Tests\Sync\Entity\Provider;

use Salient\Contract\Sync\SyncContextInterface;
use Salient\Contract\Sync\SyncProviderInterface;
use Salient\Tests\Sync\Entity\Photo;

/**
 * Syncs Photo objects with a backend
 *
 * @method Photo createPhoto(SyncContextInterface $ctx, Photo $photo)
 * @method Photo getPhoto(SyncContextInterface $ctx, int|string|null $id)
 * @method Photo updatePhoto(SyncContextInterface $ctx, Photo $photo)
 * @method Photo deletePhoto(SyncContextInterface $ctx, Photo $photo)
 * @method iterable<array-key,Photo> getPhotos(SyncContextInterface $ctx)
 *
 * @generated
 */
interface PhotoProvider extends SyncProviderInterface {}
