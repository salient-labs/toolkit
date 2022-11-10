<?php

declare(strict_types=1);

namespace Lkrms\Sync\Support;

use Lkrms\Concern\TFullyReadable;
use Lkrms\Console\ConsoleLevel;
use Lkrms\Contract\IImmutable;
use Lkrms\Contract\IReadable;
use Lkrms\Facade\Console;
use Lkrms\Facade\Convert;
use Lkrms\Sync\Concept\SyncEntity;
use Lkrms\Sync\Contract\ISyncProvider;

/**
 * An error that occurred during a sync operation
 *
 * @property-read int $ErrorType One of the SyncErrorType values
 * @property-read string $Message An sprintf() format string that explains the error
 * @property-read array $Values Values passed to sprintf() with the message format string
 * @property-read int $Level One of the ConsoleLevel values
 * @property-read SyncEntity|null $Entity The entity associated with the error
 * @property-read string|null $EntityName The display name of the entity associated with the error
 * @property-read ISyncProvider|null $Provider The sync provider associated with the error
 */
final class SyncError implements IReadable, IImmutable
{
    use TFullyReadable;

    /**
     * One of the SyncErrorType values
     *
     * @var int
     * @see SyncErrorType
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
     * @var array
     * @see SyncError::$Message
     * @see SyncError::$EntityName
     */
    protected $Values;

    /**
     * One of the ConsoleLevel values
     *
     * @var int
     * @see ConsoleLevel
     */
    protected $Level;

    /**
     * The entity associated with the error
     *
     * @var SyncEntity|null
     */
    protected $Entity;

    /**
     * The display name of the entity associated with the error
     *
     * Used in messages and summaries. Default: `<Entity>->uri()`
     *
     * @var string|null
     * @see SyncEntity::uri()
     */
    protected $EntityName;

    /**
     * The sync provider associated with the error
     *
     * @var ISyncProvider|null
     */
    protected $Provider;

    public function __construct(int $errorType, string $message, array $values = [], int $level = ConsoleLevel::ERROR, ?SyncEntity $entity = null, ?string $entityName = null, ?ISyncProvider $provider = null)
    {
        $this->EntityName = ($entityName ?: ($entity ? $entity->uri() : null));
        $this->ErrorType  = $errorType;
        $this->Message    = $message;
        $this->Values     = $values ?: [$this->EntityName];
        $this->Level      = $level;
        $this->Entity     = $entity;
        $this->Provider   = $provider ?: ($entity ? $entity->provider() : null);
    }

    public function getCode(): string
    {
        return ConsoleLevel::toCode($this->Level, 2) . sprintf("-%04d", $this->ErrorType);
    }

    /**
     * @return $this
     */
    public function toConsole(bool $once = true)
    {
        $args = [
            $this->Level,
            "[" . SyncErrorType::toName($this->ErrorType) . "]",
            sprintf($this->Message, ...Convert::toScalarArray($this->Values)),
        ];
        if ($once)
        {
            Console::messageOnce(...$args);
        }
        else
        {
            Console::message(...$args);
        }

        return $this;
    }

    /**
     * Use a fluent interface to create a new SyncError object
     *
     */
    public static function build(): SyncErrorBuilder
    {
        return new SyncErrorBuilder();
    }

}
