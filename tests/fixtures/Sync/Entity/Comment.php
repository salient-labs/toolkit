<?php declare(strict_types=1);

namespace Lkrms\Tests\Sync\Entity;

use Lkrms\Support\Catalog\RelationshipType;
use Lkrms\Sync\Concept\SyncEntity;

/**
 * Represents the state of a Comment entity in a backend
 *
 * @generated
 */
class Comment extends SyncEntity
{
    /**
     * @var int|string|null
     */
    public $Id;

    /**
     * @var Post|null
     */
    public $Post;

    /**
     * @var string|null
     */
    public $Name;

    /**
     * @var string|null
     */
    public $Email;

    /**
     * @var string|null
     */
    public $Body;

    /**
     * @inheritDoc
     */
    public static function getRelationships(): array
    {
        return [
            'Post' => [RelationshipType::ONE_TO_ONE => Post::class],
        ];
    }
}
