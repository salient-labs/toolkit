<?php declare(strict_types=1);

namespace Lkrms\Sync\Exception;

use Lkrms\Sync\Contract\ISyncEntity;
use Lkrms\Sync\Contract\ISyncProvider;
use Throwable;

/**
 * Thrown when an entity has invalid data or is not the droid you're looking for
 */
class SyncInvalidEntityException extends SyncException
{
    protected ISyncProvider $Provider;

    protected string $EntityType;

    /**
     * @var int|string|null
     */
    protected $EntityId;

    protected ?ISyncEntity $Entity;

    /**
     * Creates a new SyncInvalidEntityException object
     *
     * @template T of ISyncEntity
     *
     * @param class-string<T> $entityType
     * @param T|int|string|null $entityOrId
     */
    public function __construct(
        string $message,
        ISyncProvider $provider,
        string $entityType,
        $entityOrId,
        ?Throwable $previous = null
    ) {
        $this->Provider = $provider;
        $this->EntityType = $entityType;

        if ($entityOrId instanceof ISyncEntity) {
            $this->Entity = $entityOrId;
            $this->EntityId = $this->Entity->id();
        } else {
            $this->EntityId = $entityOrId;
            $this->Entity = null;
        }

        parent::__construct($message, $previous);
    }
}
