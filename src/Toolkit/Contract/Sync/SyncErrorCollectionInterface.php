<?php declare(strict_types=1);

namespace Salient\Contract\Sync;

use Salient\Contract\Collection\CollectionInterface;
use Salient\Contract\Console\ConsoleWriterInterface;
use Stringable;

/**
 * @extends CollectionInterface<int,SyncErrorInterface>
 */
interface SyncErrorCollectionInterface extends CollectionInterface, Stringable
{
    /**
     * Get the number of errors in the collection
     */
    public function getErrorCount(): int;

    /**
     * Get the number of warnings in the collection
     */
    public function getWarningCount(): int;

    /**
     * Get a JSON:API-compatible representation of errors in the collection
     * after grouping them by level, type and message
     *
     * @return array<array{code:string,title:string,detail:string,meta:array{level:string,count:int,seen:int,values:list<mixed[]|object|int|float|string|bool|null>}}>
     */
    public function getSummary(): array;

    /**
     * Get a human-readable representation of errors in the collection after
     * grouping them by level, type and message
     */
    public function getSummaryText(): string;

    /**
     * Write errors in the collection to the console after grouping them by
     * level, type and message
     *
     * Output is written to the console with level:
     * - NOTICE if no errors have been recorded
     * - ERROR if one or more recorded errors have level ERROR or higher
     * - WARNING if all recorded errors have level WARNING or lower
     *
     * @param ConsoleWriterInterface|null $writer If `null`, the default console
     * writer is used.
     */
    public function reportErrors(
        ?ConsoleWriterInterface $writer = null,
        string $successText = 'No sync errors recorded'
    ): void;

    /**
     * @return array<array{code:string,title:string,detail:string,meta:array{level:string,count:int,seen:int,values:list<mixed[]|object|int|float|string|bool|null>}}>
     */
    public function jsonSerialize(): array;
}
