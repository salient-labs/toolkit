<?php declare(strict_types=1);

namespace Salient\Contract\Sync;

/**
 * @api
 */
interface SyncOperation
{
    /**
     * Add an entity to the backend
     */
    public const CREATE = 1;

    /**
     * Get an entity from the backend
     */
    public const READ = 2;

    /**
     * Update an entity in the backend
     */
    public const UPDATE = 4;

    /**
     * Delete an entity from the backend
     */
    public const DELETE = 8;

    /**
     * Add a list of entities to the backend
     */
    public const CREATE_LIST = 16;

    /**
     * Get a list of entities from the backend
     */
    public const READ_LIST = 32;

    /**
     * Update a list of entities in the backend
     */
    public const UPDATE_LIST = 64;

    /**
     * Delete a list of entities from the backend
     */
    public const DELETE_LIST = 128;
}
