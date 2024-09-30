<?php declare(strict_types=1);

namespace Salient\Tests\Sync\Entity;

use Salient\Sync\Support\DeferredEntity;
use Salient\Sync\AbstractSyncEntity;

/**
 * Represents the state of a Photo entity in a backend
 *
 * @generated
 */
class Photo extends AbstractSyncEntity
{
    /** @var int|string|null */
    public $Id;
    /** @var Album|DeferredEntity<Album>|null */
    public $Album;
    /** @var string|null */
    public $Title;
    /** @var string|null */
    public $Url;
    /** @var string|null */
    public $ThumbnailUrl;

    /**
     * @internal
     */
    public static function getRelationships(): array
    {
        return [
            'Album' => [self::ONE_TO_ONE => Album::class],
        ];
    }
}
