<?php declare(strict_types=1);

namespace Salient\Sync\Reflection;

use Salient\Contract\Sync\SyncEntityInterface;
use ReflectionClass;

/**
 * @template TEntity of SyncEntityInterface
 *
 * @extends ReflectionClass<TEntity>
 */
class ReflectionSyncEntity extends ReflectionClass
{
    use SyncReflectionTrait;

    /**
     * @param TEntity|class-string<TEntity> $entity
     */
    public function __construct($entity)
    {
        $this->assertImplements($entity, SyncEntityInterface::class);
        parent::__construct($entity);
    }

    /**
     * Get the plural form of the entity's short name
     *
     * Returns `null` if {@see SyncEntityInterface::getPlural()} returns `null`
     * or the short name of the entity.
     */
    public function getPluralName(): ?string
    {
        /** @var string|null */
        $plural = $this->getMethod('getPlural')->invoke(null);
        if ($plural !== null && !strcasecmp($plural, $this->getShortName())) {
            return null;
        }
        return $plural;
    }
}
