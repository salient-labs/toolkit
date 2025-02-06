<?php declare(strict_types=1);

namespace Salient\Sync;

use Salient\Contract\Catalog\MessageLevel as Level;
use Salient\Contract\Core\Buildable;
use Salient\Contract\Sync\ErrorType;
use Salient\Contract\Sync\SyncEntityInterface;
use Salient\Contract\Sync\SyncErrorInterface;
use Salient\Contract\Sync\SyncProviderInterface;
use Salient\Core\Concern\BuildableTrait;
use Salient\Core\Concern\ImmutableTrait;
use Salient\Utility\Arr;

/**
 * An error that occurred during a sync operation
 *
 * @implements Buildable<SyncErrorBuilder>
 */
final class SyncError implements SyncErrorInterface, Buildable
{
    /** @use BuildableTrait<SyncErrorBuilder> */
    use BuildableTrait;
    use ImmutableTrait;

    /** @var ErrorType::* */
    private int $ErrorType;
    private string $Message;
    /** @var list<mixed[]|object|int|float|string|bool|null> */
    private array $Values;
    /** @var Level::* */
    private int $Level;
    private ?SyncEntityInterface $Entity;
    private ?string $EntityName;
    private ?SyncProviderInterface $Provider;
    private int $Count = 1;

    /**
     * @internal
     *
     * @param ErrorType::* $errorType Error type.
     * @param string $message `sprintf()` format string that explains the error.
     * @param list<mixed[]|object|int|float|string|bool|null>|null $values Values applied to the message format string. Default: `[$entityName]`
     * @param Level::* $level Error severity/message level.
     * @param SyncEntityInterface|null $entity Entity associated with the error.
     * @param string|null $entityName Display name of the entity associated with the error. Default: `$entity->getUri()`
     * @param SyncProviderInterface|null $provider Sync provider associated with the error. Default: `$entity->getProvider()`
     */
    public function __construct(
        int $errorType,
        string $message,
        ?array $values = null,
        int $level = Level::ERROR,
        ?SyncEntityInterface $entity = null,
        ?string $entityName = null,
        ?SyncProviderInterface $provider = null
    ) {
        $provider ??= ($entity ? $entity->getProvider() : null);
        $entityName ??= ($entity
            ? $entity->getUri($provider ? $provider->getStore() : null)
            : null);

        $this->ErrorType = $errorType;
        $this->Message = $message;
        $this->Values = $values ?? [$entityName];
        $this->Level = $level;
        $this->Entity = $entity;
        $this->EntityName = $entityName;
        $this->Provider = $provider;
    }

    /**
     * @inheritDoc
     */
    public function getType(): int
    {
        return $this->ErrorType;
    }

    /**
     * @inheritDoc
     */
    public function getLevel(): int
    {
        return $this->Level;
    }

    /**
     * @inheritDoc
     */
    public function getCode(): string
    {
        return sprintf('%02d-%04d', $this->Level, $this->ErrorType);
    }

    /**
     * @inheritDoc
     */
    public function getMessage(): string
    {
        return sprintf($this->Message, ...Arr::toScalars($this->Values));
    }

    /**
     * @inheritDoc
     */
    public function getFormat(): string
    {
        return $this->Message;
    }

    /**
     * @inheritDoc
     */
    public function getValues(): array
    {
        return $this->Values;
    }

    /**
     * @inheritDoc
     */
    public function getProvider(): ?SyncProviderInterface
    {
        return $this->Provider;
    }

    /**
     * @inheritDoc
     */
    public function getEntity(): ?SyncEntityInterface
    {
        return $this->Entity;
    }

    /**
     * @inheritDoc
     */
    public function getEntityName(): ?string
    {
        return $this->EntityName;
    }

    /**
     * @inheritDoc
     */
    public function getCount(): int
    {
        return $this->Count;
    }

    /**
     * @inheritDoc
     */
    public function count()
    {
        return $this->with('Count', $this->Count + 1);
    }

    /**
     * @inheritDoc
     */
    public static function compare($a, $b): int
    {
        return $a->ErrorType <=> $b->ErrorType
            ?: $a->Level <=> $b->Level
            ?: $a->Message <=> $b->Message
            ?: $a->Values <=> $b->Values
            ?: $a->Provider <=> $b->Provider
            ?: $a->Entity <=> $b->Entity
            ?: $a->EntityName <=> $b->EntityName;
    }
}
