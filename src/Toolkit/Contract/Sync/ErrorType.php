<?php declare(strict_types=1);

namespace Salient\Contract\Sync;

interface ErrorType
{
    /**
     * No entities matching the criteria were returned by the provider
     */
    public const ENTITY_NOT_FOUND = 0;

    /**
     * The same entity appears multiple times
     */
    public const ENTITY_NOT_UNIQUE = 1;

    /**
     * The entity should not exist, or has a missing counterpart
     */
    public const ENTITY_NOT_EXPECTED = 2;

    /**
     * The entity contains invalid data
     */
    public const ENTITY_NOT_VALID = 3;

    /**
     * The provider does not implement sync operations for the entity
     */
    public const ENTITY_NOT_SUPPORTED = 4;

    /**
     * Hierarchical data contains a circular reference
     */
    public const HIERARCHY_IS_CIRCULAR = 5;

    /**
     * The provider cannot establish a connection to the backend
     */
    public const BACKEND_UNREACHABLE = 6;
}
