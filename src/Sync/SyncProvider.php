<?php

declare(strict_types=1);

namespace Lkrms\Sync;

/**
 *
 * @package Lkrms
 */
abstract class SyncProvider
{
    /**
     * Returns a stable value unique to the connected backend instance
     *
     * The value returned should be the canonical form of the smallest possible
     * set of stable metadata that uniquely identifies the specific data source
     * the provider is currently connected to.
     *
     * This could include:
     * - an endpoint URI (if backend instances are URI-specific or can be
     *   expressed as an immutable URI)
     * - a tenant ID
     * - an installation GUID
     *
     * It must NOT include:
     * - unstable values like usernames, tokens and other identifiers with a
     *   shorter lifespan than the data source itself
     * - values that aren't unique to the connected data source
     * - case-insensitive values (unless normalised first)
     *
     * @return string|string[]
     */
    abstract public function getBackendIdentifier(): string;

    /**
     * Builds a capability map based on the provider's public methods
     *
     * Returns an array where each {@see SyncOperation} implemented by the
     * provider appears in a list of capabilities for the associated
     * {@see SyncEntity}.
     *
     * @return array<string,int[]> A map from entity names to capability lists.
     */
    public function getCapabilities(): array
    {
        /**
         * @todo Implement this
         */
        return [];
    }
}

