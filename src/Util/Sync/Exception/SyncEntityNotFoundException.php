<?php declare(strict_types=1);

namespace Lkrms\Sync\Exception;

use Lkrms\Sync\Contract\ISyncEntity;
use Lkrms\Sync\Contract\ISyncProvider;
use Lkrms\Utility\Format;
use Throwable;

/**
 * Thrown when an entity doesn't exist in a backend
 */
class SyncEntityNotFoundException extends SyncException
{
    /**
     * @param class-string<ISyncEntity> $entity
     * @param int|string|array<string,mixed> $id
     */
    public function __construct(
        ISyncProvider $provider,
        string $entity,
        $id,
        ?Throwable $previous = null
    ) {
        if (!is_array($id)) {
            $id = ['ID' => $id];
        }
        $id = substr(Format::array($id, "%s '%s', "), 0, -2);
        parent::__construct(
            sprintf(
                '%s could not find %s with %s',
                get_class($provider),
                $entity,
                $id,
            ),
            $previous,
        );
    }
}
