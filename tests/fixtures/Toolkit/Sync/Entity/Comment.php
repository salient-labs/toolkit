<?php declare(strict_types=1);

namespace Salient\Tests\Sync\Entity;

use Salient\Contract\Core\Cardinality;
use Salient\Sync\Support\DeferredEntity;
use Salient\Sync\AbstractSyncEntity;

/**
 * Represents the state of a Comment entity in a backend
 *
 * @generated
 */
class Comment extends AbstractSyncEntity
{
    /** @var int|string|null */
    public $Id;
    /** @var Post|DeferredEntity<Post>|null */
    public $Post;
    /** @var string|null */
    public $Name;
    /** @var string|null */
    public $Email;
    /** @var string|null */
    public $Body;

    /**
     * @internal
     */
    public static function getRelationships(): array
    {
        return [
            'Post' => [Cardinality::ONE_TO_ONE => Post::class],
        ];
    }
}
