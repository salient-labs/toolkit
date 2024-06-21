<?php declare(strict_types=1);

namespace Salient\Sync\Exception;

use Salient\Contract\Sync\SyncEntityInterface;
use Salient\Contract\Sync\SyncProviderInterface;
use Throwable;

/**
 * Thrown when an entity has invalid data or is not the droid you're looking for
 */
class SyncInvalidEntityException extends AbstractSyncException
{
    protected SyncProviderInterface $Provider;
    protected string $EntityType;
    /** @var int|string|null */
    protected $EntityId;
    protected ?SyncEntityInterface $Entity;

    /**
     * Creates a new SyncInvalidEntityException object
     *
     * @template T of SyncEntityInterface
     *
     * @param class-string<T> $entityType
     * @param T|int|string|null $entityOrId
     */
    public function __construct(
        string $message,
        SyncProviderInterface $provider,
        string $entityType,
        $entityOrId,
        ?Throwable $previous = null
    ) {
        $this->Provider = $provider;
        $this->EntityType = $entityType;

        if ($entityOrId instanceof SyncEntityInterface) {
            $this->Entity = $entityOrId;
            $this->EntityId = $this->Entity->getId();
        } else {
            $this->EntityId = $entityOrId;
            $this->Entity = null;
        }

        parent::__construct($message, $previous);
    }
}
