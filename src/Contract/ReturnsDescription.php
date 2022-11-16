<?php

declare(strict_types=1);

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
     * @param int|null $maxLength Advisory only. The return value will be
     * truncated to `$maxLength` bytes if set.
     */
    public function name(?int $maxLength = null): ?string;

    /**
     * Get a brief description of the object
     *
     * @param int|null $maxLength Advisory only. The return value will be
     * truncated to `$maxLength` bytes if set.
     */
    public function description(?int $maxLength = null): ?string;

}
