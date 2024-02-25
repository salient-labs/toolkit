<?php declare(strict_types=1);

namespace Lkrms\Tests\Sync\Entity;

use Salient\Core\Catalog\Cardinality;
use Salient\Sync\Concept\SyncEntity;
use Salient\Sync\Support\DeferredEntity;
use Salient\Sync\Support\DeferredRelationship;

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
     * @var User|DeferredEntity<User>|null
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
     * @var array<Comment|DeferredEntity<Comment>>|DeferredRelationship<Comment>|null
     */
    public $Comments;

    /**
     * @internal
     */
    public static function getRelationships(): array
    {
        return [
            'User' => [Cardinality::ONE_TO_ONE => User::class],
            'Comments' => [Cardinality::ONE_TO_MANY => Comment::class],
        ];
    }
}
