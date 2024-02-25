<?php declare(strict_types=1);

namespace Lkrms\Tests\Sync\Entity\Provider;

use Lkrms\Tests\Sync\Entity\Album;
use Salient\Sync\Contract\ISyncContext;
use Salient\Sync\Contract\ISyncProvider;

/**
 * Syncs Album objects with a backend
 *
 * @method Album createAlbum(ISyncContext $ctx, Album $album)
 * @method Album getAlbum(ISyncContext $ctx, int|string|null $id)
 * @method Album updateAlbum(ISyncContext $ctx, Album $album)
 * @method Album deleteAlbum(ISyncContext $ctx, Album $album)
 * @method iterable<Album> getAlbums(ISyncContext $ctx)
 *
 * @generated
 */
interface AlbumProvider extends ISyncProvider {}
