<?php declare(strict_types=1);

namespace Salient\Core\Contract;

/**
 * @api
 */
interface SerializeRulesInterface
{
    /**
     * Override the default date formatter
     */
    public function getDateFormatter(): ?DateFormatterInterface;

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
     * Set the value of IncludeMeta on a copy of the instance
     *
     * @return static
     */
    public function withIncludeMeta(?bool $value);

    /**
     * Set the value of SortByKey on a copy of the instance
     *
     * @return static
     */
    public function withSortByKey(?bool $value);

    /**
     * Set the value of MaxDepth on a copy of the instance
     *
     * @return static
     */
    public function withMaxDepth(?int $value);
}
