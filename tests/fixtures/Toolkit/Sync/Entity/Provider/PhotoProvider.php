<?php declare(strict_types=1);

namespace Salient\Tests\Sync\Entity\Provider;

use Salient\Sync\Contract\SyncContextInterface;
use Salient\Sync\Contract\SyncProviderInterface;
use Salient\Tests\Sync\Entity\Photo;

/**
 * Syncs Photo objects with a backend
 *
 * @method Photo createPhoto(SyncContextInterface $ctx, Photo $photo)
 * @method Photo getPhoto(SyncContextInterface $ctx, int|string|null $id)
 * @method Photo updatePhoto(SyncContextInterface $ctx, Photo $photo)
 * @method Photo deletePhoto(SyncContextInterface $ctx, Photo $photo)
 * @method iterable<Photo> getPhotos(SyncContextInterface $ctx)
 *
 * @generated
 */
interface PhotoProvider extends SyncProviderInterface {}
