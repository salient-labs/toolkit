<?php declare(strict_types=1);

namespace Salient\Sync\Exception;

use Salient\Contract\Sync\Exception\SyncEntityNotFoundExceptionInterface;
use Salient\Contract\Sync\SyncEntityInterface;
use Salient\Contract\Sync\SyncProviderInterface;
use Salient\Utility\Format;
use Throwable;

/**
 * Thrown when an entity doesn't exist in a backend
 */
class SyncEntityNotFoundException extends AbstractSyncException implements SyncEntityNotFoundExceptionInterface
{
    /**
     * @param class-string<SyncEntityInterface> $entity
     * @param int|string|array<string,mixed> $id
     */
    public function __construct(
        SyncProviderInterface $provider,
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
