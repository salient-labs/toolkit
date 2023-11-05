<?php declare(strict_types=1);

namespace Lkrms\Tests\Sync\Entity\Provider;

use Lkrms\Sync\Contract\ISyncContext;
use Lkrms\Sync\Contract\ISyncProvider;
use Lkrms\Tests\Sync\Entity\Photo;

/**
 * Syncs Photo objects with a backend
 *
 * @method Photo createPhoto(ISyncContext $ctx, Photo $photo)
 * @method Photo getPhoto(ISyncContext $ctx, int|string|null $id)
 * @method Photo updatePhoto(ISyncContext $ctx, Photo $photo)
 * @method Photo deletePhoto(ISyncContext $ctx, Photo $photo)
 * @method iterable<Photo> getPhotos(ISyncContext $ctx)
 */
interface PhotoProvider extends ISyncProvider {}
