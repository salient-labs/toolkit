<?php declare(strict_types=1);

namespace Lkrms\Tests\Sync\Entity;

use Lkrms\Support\Catalog\RelationshipType;
use Lkrms\Sync\Concept\SyncEntity;
use Lkrms\Sync\Support\DeferredEntity;
use Lkrms\Sync\Support\DeferredRelationship;

/**
 * Represents the state of a User entity in a backend
 *
 * @generated
 */
class User extends SyncEntity
{
    /**
     * @var int|string|null
     */
    public $Id;

    /**
     * @var string|null
     */
    public $Name;

    /**
     * @var string|null
     */
    public $Username;

    /**
     * @var string|null
     */
    public $Email;

    /**
     * @var mixed[]|null
     */
    public $Address;

    /**
     * @var string|null
     */
    public $Phone;

    /**
     * @var mixed[]|null
     */
    public $Company;

    /**
     * @var array<Task|DeferredEntity<Task>>|DeferredRelationship<Task>|null
     */
    public $Tasks;

    /**
     * @var array<Post|DeferredEntity<Post>>|DeferredRelationship<Post>|null
     */
    public $Posts;

    /**
     * @var array<Album|DeferredEntity<Album>>|DeferredRelationship<Album>|null
     */
    public $Albums;

    /**
     * @internal
     */
    public static function getRelationships(): array
    {
        return [
            'Tasks' => [RelationshipType::ONE_TO_MANY => Task::class],
            'Posts' => [RelationshipType::ONE_TO_MANY => Post::class],
            'Albums' => [RelationshipType::ONE_TO_MANY => Album::class],
        ];
    }
}
