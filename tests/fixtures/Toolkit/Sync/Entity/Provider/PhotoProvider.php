<?php declare(strict_types=1);

namespace Salient\Tests\Sync\Entity\Provider;

use Salient\Sync\Contract\ISyncContext;
use Salient\Sync\Contract\ISyncProvider;
use Salient\Tests\Sync\Entity\Photo;

/**
 * Syncs Photo objects with a backend
 *
 * @method Photo createPhoto(ISyncContext $ctx, Photo $photo)
 * @method Photo getPhoto(ISyncContext $ctx, int|string|null $id)
 * @method Photo updatePhoto(ISyncContext $ctx, Photo $photo)
 * @method Photo deletePhoto(ISyncContext $ctx, Photo $photo)
 * @method iterable<Photo> getPhotos(ISyncContext $ctx)
 *
 * @generated
 */
interface PhotoProvider extends ISyncProvider {}
