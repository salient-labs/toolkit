<?php declare(strict_types=1);

namespace Lkrms\Tests\Sync\Entity;

use Lkrms\Support\Catalog\RelationshipType;
use Lkrms\Sync\Concept\SyncEntity;

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
     * @var Task[]|null
     */
    public $Tasks;

    /**
     * @var Post[]|null
     */
    public $Posts;

    /**
     * @var Album[]|null
     */
    public $Albums;

    /**
     * @inheritDoc
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
