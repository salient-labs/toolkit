<?php declare(strict_types=1);

namespace Lkrms\Sync\Support;

use Lkrms\Concern\HasBuilder;
use Lkrms\Concern\TFullyReadable;
use Lkrms\Console\Catalog\ConsoleLevel;
use Lkrms\Contract\Buildable;
use Lkrms\Contract\IComparable;
use Lkrms\Contract\IImmutable;
use Lkrms\Contract\IReadable;
use Lkrms\Sync\Catalog\SyncErrorType;
use Lkrms\Sync\Contract\ISyncEntity;
use Lkrms\Sync\Contract\ISyncProvider;

/**
 * An error that occurred during a sync operation
 *
 * @property-read SyncErrorType::* $ErrorType
 * @property-read string $Message An sprintf() format string that explains the error
 * @property-read mixed[] $Values Values passed to sprintf() with the message format string
 * @property-read ConsoleLevel::* $Level
 * @property-read ISyncEntity|null $Entity The entity associated with the error
 * @property-read string|null $EntityName The display name of the entity associated with the error
 * @property-read ISyncProvider|null $Provider The sync provider associated with the error
 * @property-read int $Count How many times the error has been reported
 *
 * @implements Buildable<SyncErrorBuilder>
 */
final class SyncError implements IReadable, IComparable, IImmutable, Buildable
{
    use TFullyReadable;
    use HasBuilder;

    /**
     * @var SyncErrorType::*
     */
    protected $ErrorType;

    /**
     * An sprintf() format string that explains the error
     *
     * Example: `"Contact not returned by provider: %s"`
     *
     * Values for `sprintf()` specifiers are taken from the
     * {@see SyncError::$Values} array, which contains
     * {@see SyncError::$EntityName} by default.
     *
     * @var string
     */
    protected $Message;

    /**
     * Values passed to sprintf() with the message format string
     *
     * Default: `[ "<EntityName>" ]`
     *
     * @var mixed[]
     * @see SyncError::$Message
     * @see SyncError::$EntityName
     */
    protected $Values;

    /**
     * @var ConsoleLevel::*
     */
    protected $Level;

    /**
     * The entity associated with the error
     *
     * @var ISyncEntity|null
     */
    protected $Entity;

    /**
     * The display name of the entity associated with the error
     *
     * Used in messages and summaries. Default: `<Entity>->uri()`
     *
     * @var string|null
     * @see ISyncEntity::uri()
     */
    protected $EntityName;

    /**
     * The sync provider associated with the error
     *
     * @var ISyncProvider|null
     */
    protected $Provider;

    /**
     * How many times the error has been reported
     *
     * @var int
     */
    protected $Count = 1;

    /**
     * @param SyncErrorType::* $errorType
     * @param mixed[] $values
     * @param ConsoleLevel::* $level
     */
    public function __construct(
        int $errorType,
        string $message,
        array $values = [],
        int $level = ConsoleLevel::ERROR,
        ?ISyncEntity $entity = null,
        ?string $entityName = null,
        ?ISyncProvider $provider = null
    ) {
        $this->EntityName = $entityName ?? ($entity ? $entity->uri() : null);
        $this->ErrorType = $errorType;
        $this->Message = $message;
        $this->Values = $values ?: [$this->EntityName];
        $this->Level = $level;
        $this->Entity = $entity;
        $this->Provider = $provider ?? ($entity ? $entity->provider() : null);
    }

    /**
     * @return $this
     */
    public function count()
    {
        $this->Count++;

        return $this;
    }

    public static function compare($a, $b): int
    {
        return $a->Level <=> $b->Level
            ?: $a->ErrorType <=> $b->ErrorType
            ?: $a->Message <=> $b->Message
            ?: $a->Values <=> $b->Values
            ?: $a->EntityName <=> $b->EntityName
            ?: ($a->Provider ? $a->Provider->getProviderId() : null) <=> ($b->Provider ? $b->Provider->getProviderId() : null)
            ?: ($a->Entity ? $a->Entity->id() : null) <=> ($b->Entity ? $b->Entity->id() : null);
    }

    public function getCode(): string
    {
        return sprintf('%02d-%04d', $this->Level, $this->ErrorType);
    }
}
