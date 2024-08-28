<?php declare(strict_types=1);

namespace Salient\Sync\Exception;

use Salient\Contract\Sync\SyncEntityInterface;
use Salient\Contract\Sync\SyncProviderInterface;
use Throwable;

/**
 * @api
 */
class SyncInvalidEntityException extends AbstractSyncException
{
    protected SyncProviderInterface $Provider;
    /** @var class-string<SyncEntityInterface> */
    protected string $EntityType;
    /** @var int|string|null */
    protected $EntityId;
    protected ?SyncEntityInterface $Entity;

    /**
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
            $this->EntityId = $entityOrId->getId();
            $this->Entity = $entityOrId;
        } else {
            $this->EntityId = $entityOrId;
            $this->Entity = null;
        }

        parent::__construct($message, $previous);
    }

    public function getProvider(): SyncProviderInterface
    {
        return $this->Provider;
    }

    public function getEntityType(): string
    {
        return $this->EntityType;
    }

    /**
     * @return int|string|null
     */
    public function getEntityId()
    {
        return $this->EntityId;
    }

    public function getEntity(): ?SyncEntityInterface
    {
        return $this->Entity;
    }

    /**
     * @inheritDoc
     */
    public function getMetadata(): array
    {
        return [
            'Provider' => $this->getProviderName($this->Provider),
            'Entity' => $this->EntityType,
            'EntityId' => $this->EntityId,
        ];
    }
}
