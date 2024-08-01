<?php declare(strict_types=1);

namespace Salient\Contract\Sync;

/**
 * Sync operation types
 */
interface SyncOperation
{
    /**
     * Add an entity to the backend
     *
     * Typically corresponds to:
     * - `POST /<entity_name>`
     * - `INSERT INTO <entity_name> ...`
     */
    public const CREATE = 1;

    /**
     * Get an entity from the backend
     *
     * Typically corresponds to:
     * - `GET /<entity_name>/<id>`
     * - `SELECT ... FROM <entity_name> WHERE <id_field> = <id>`
     */
    public const READ = 2;

    /**
     * Update an entity in the backend
     *
     * Typically corresponds to:
     * - `PUT /<entity_name>/<id>`
     * - `PATCH /<entity_name>/<id>`
     * - `UPDATE <entity_name> WHERE ...`
     */
    public const UPDATE = 4;

    /**
     * Delete an entity from the backend
     *
     * Typically corresponds to:
     * - `DELETE /<entity_name>/<id>`
     * - `DELETE FROM <entity_name> WHERE ...`
     */
    public const DELETE = 8;

    /**
     * Add a list of entities to the backend
     *
     * Typically corresponds to:
     * - `POST /<entity_name>`
     * - `INSERT INTO <entity_name> ...`
     */
    public const CREATE_LIST = 16;

    /**
     * Get a list of entities from the backend
     *
     * Typically corresponds to:
     * - `GET /<entity_name>`
     * - `SELECT ... FROM <entity_name>`
     */
    public const READ_LIST = 32;

    /**
     * Update a list of entities in the backend
     *
     * Typically corresponds to:
     * - `PUT /<entity_name>`
     * - `PATCH /<entity_name>`
     * - `UPDATE <entity_name> WHERE ...`
     */
    public const UPDATE_LIST = 64;

    /**
     * Delete a list of entities from the backend
     *
     * Typically corresponds to:
     * - `DELETE /<entity_name>`
     * - `DELETE FROM <entity_name> WHERE ...`
     */
    public const DELETE_LIST = 128;
}
