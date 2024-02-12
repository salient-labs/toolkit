<?php declare(strict_types=1);

namespace Lkrms\Tests\Sync\Entity;

use Lkrms\Support\Catalog\RelationshipType;
use Lkrms\Sync\Concept\SyncEntity;

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
     * @var User|null
     */
    public $User;

    /**
     * @var string|null
     */
    public $Title;

    /**
     * @var Photo[]|null
     */
    public $Photos;

    /**
     * @inheritDoc
     */
    public static function getRelationships(): array
    {
        return [
            'User' => [RelationshipType::ONE_TO_ONE => User::class],
            'Photos' => [RelationshipType::ONE_TO_MANY => Photo::class],
        ];
    }
}
