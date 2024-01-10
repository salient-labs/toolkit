<?php declare(strict_types=1);

namespace Lkrms\Contract;

use Lkrms\Support\Date\DateFormatter;

/**
 * Instructions for serializing nested entities
 */
interface ISerializeRules
{
    /**
     * Override the default date formatter
     */
    public function getDateFormatter(): ?DateFormatter;

    /**
     * Include undeclared property values?
     */
    public function getIncludeMeta(): bool;

    /**
     * Sort arrays by key?
     */
    public function getSortByKey(): bool;

    /**
     * Throw an exception when values are nested beyond this depth
     */
    public function getMaxDepth(): ?int;

    /**
     * Check for recursion?
     */
    public function getDetectRecursion(): bool;

    /**
     * A bitmask of enabled flags
     */
    public function getFlags(): int;

    /**
     * Set the value of IncludeMeta on a copy of the instance
     *
     * @return $this
     */
    public function withIncludeMeta(?bool $value);

    /**
     * Set the value of SortByKey on a copy of the instance
     *
     * @return $this
     */
    public function withSortByKey(?bool $value);

    /**
     * Set the value of MaxDepth on a copy of the instance
     *
     * @return $this
     */
    public function withMaxDepth(?int $value);
}
