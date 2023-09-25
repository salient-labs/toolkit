<?php declare(strict_types=1);

namespace Lkrms\Sync\Exception;

use Lkrms\Sync\Contract\ISyncEntity;
use Lkrms\Sync\Contract\ISyncProvider;

/**
 * Thrown when an entity doesn't exist in a backend
 */
class SyncEntityNotFoundException extends SyncException
{
    /**
     * @param class-string<ISyncEntity> $entity
     * @param int|string $id
     */
    public function __construct(ISyncProvider $provider, string $entity, $id)
    {
        parent::__construct(sprintf(
            "%s could not find %s with ID '%s'",
            get_class($provider),
            $entity,
            $id
        ));
    }
}
