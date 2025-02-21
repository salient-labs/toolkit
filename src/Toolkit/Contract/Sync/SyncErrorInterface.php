<?php declare(strict_types=1);

namespace Salient\Contract\Sync;

use Salient\Contract\Console\ConsoleInterface as Console;
use Salient\Contract\Core\Comparable;
use Salient\Contract\Core\Immutable;

interface SyncErrorInterface extends Comparable, Immutable
{
    /**
     * Get the error's type
     *
     * @return ErrorType::*
     */
    public function getType(): int;

    /**
     * Get the error's severity/message level
     *
     * @return Console::LEVEL_*
     */
    public function getLevel(): int;

    /**
     * Get a code unique to the error's type and severity/message level
     */
    public function getCode(): string;

    /**
     * Get an explanation of the error
     */
    public function getMessage(): string;

    /**
     * Get an sprintf() format string that explains the error
     */
    public function getFormat(): string;

    /**
     * Get values applied to the message format string
     *
     * @return list<mixed[]|object|int|float|string|bool|null>
     */
    public function getValues(): array;

    /**
     * Get the sync provider associated with the error
     */
    public function getProvider(): ?SyncProviderInterface;

    /**
     * Get the entity associated with the error
     */
    public function getEntity(): ?SyncEntityInterface;

    /**
     * Get the display name of the entity associated with the error
     */
    public function getEntityName(): ?string;

    /**
     * Get the number of times the error has been reported
     */
    public function getCount(): int;

    /**
     * Get an instance where the number of times the error has been reported is
     * incremented
     *
     * @return static
     */
    public function count();
}
