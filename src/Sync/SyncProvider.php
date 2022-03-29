<?php

declare(strict_types=1);

namespace Lkrms\Sync;

use Lkrms\Generate;

/**
 * Base class for API providers
 *
 * @package Lkrms
 */
abstract class SyncProvider
{
    /**
     * Returns a stable identifier unique to the connected backend instance
     *
     * This method must be idempotent for each backend instance the provider
     * connects to. The return value should correspond to the smallest possible
     * set of stable metadata that uniquely identifies the specific data source
     * backing the connected instance.
     *
     * This could include:
     * - an endpoint URI (if backend instances are URI-specific or can be
     *   expressed as an immutable URI)
     * - a tenant ID
     * - an installation GUID
     *
     * It should not include:
     * - usernames, API keys, tokens, or other identifiers with a shorter
     *   lifespan than the data source itself
     * - values that aren't unique to the connected data source
     * - case-insensitive values (unless normalised first)
     *
     * @return string[]
     */
    abstract protected function getBackendIdentifier(): array;

    /**
     * Returns a stable hash unique to the connected backend instance
     *
     * @return string
     * @see SyncProvider::getBackendIdentifier()
     */
    final public function getBackendHash(): string
    {
        return Generate::hash(...$this->getBackendIdentifier());
    }
}

