<?php declare(strict_types=1);

namespace Lkrms\Tests\Sync\Entity;

use Lkrms\Sync\Concept\SyncEntity;
use Lkrms\Sync\Support\DeferredEntity;
use Lkrms\Sync\Support\DeferredRelationship;
use Salient\Core\Catalog\Cardinality;

/**
 * Represents the state of an Album entity in a backend
 *
 * @generated
 */
class Album extends SyncEntity
{
    /**
     * @var int|string|null
     */
    public $Id;

    /**
     * @var User|DeferredEntity<User>|null
     */
    public $User;

    /**
     * @var string|null
     */
    public $Title;

    /**
     * @var array<Photo|DeferredEntity<Photo>>|DeferredRelationship<Photo>|null
     */
    public $Photos;

    /**
     * @internal
     */
    public static function getRelationships(): array
    {
        return [
            'User' => [Cardinality::ONE_TO_ONE => User::class],
            'Photos' => [Cardinality::ONE_TO_MANY => Photo::class],
        ];
    }
}
