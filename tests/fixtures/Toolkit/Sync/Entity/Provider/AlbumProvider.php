<?php declare(strict_types=1);

namespace Salient\Tests\Sync\Entity\Provider;

use Salient\Sync\Contract\SyncContextInterface;
use Salient\Sync\Contract\SyncProviderInterface;
use Salient\Tests\Sync\Entity\Album;

/**
 * Syncs Album objects with a backend
 *
 * @method Album createAlbum(SyncContextInterface $ctx, Album $album)
 * @method Album getAlbum(SyncContextInterface $ctx, int|string|null $id)
 * @method Album updateAlbum(SyncContextInterface $ctx, Album $album)
 * @method Album deleteAlbum(SyncContextInterface $ctx, Album $album)
 * @method iterable<Album> getAlbums(SyncContextInterface $ctx)
 *
 * @generated
 */
interface AlbumProvider extends SyncProviderInterface {}
