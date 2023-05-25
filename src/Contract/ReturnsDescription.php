<?php declare(strict_types=1);

namespace Lkrms\Contract;

/**
 * Returns a "friendly name" for the object
 *
 */
interface ReturnsDescription
{
    /**
     * Get the name or title of the object
     *
     * Appropriate values to return are:
     * - already in scope (no lookup required)
     * - ready to use (no formatting required)
     * - unique enough that duplicates are rare
     * - easy to read
     *
     */
    public function name(): ?string;

    /**
     * Get a brief description of the object
     *
     */
    public function description(): ?string;
}
