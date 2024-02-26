<?php declare(strict_types=1);

namespace Salient\Tests\Sync\Entity;

use Salient\Core\Catalog\Cardinality;
use Salient\Sync\Concept\SyncEntity;
use Salient\Sync\Support\DeferredEntity;
use Salient\Sync\Support\DeferredRelationship;

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
