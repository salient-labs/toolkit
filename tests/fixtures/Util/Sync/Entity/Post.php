<?php declare(strict_types=1);

namespace Lkrms\Tests\Sync\Entity;

use Lkrms\Support\Catalog\RelationshipType;
use Lkrms\Sync\Concept\SyncEntity;

/**
 * Represents the state of a Post entity in a backend
 *
 * @generated
 */
class Post extends SyncEntity
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
     * @var string|null
     */
    public $Body;

    /**
     * @var Comment[]|null
     */
    public $Comments;

    /**
     * @inheritDoc
     */
    public static function getRelationships(): array
    {
        return [
            'User' => [RelationshipType::ONE_TO_ONE => User::class],
            'Comments' => [RelationshipType::ONE_TO_MANY => Comment::class],
        ];
    }
}
