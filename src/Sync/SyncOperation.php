<?php

declare(strict_types=1);

namespace Lkrms\Sync;

/**
 *
 * @package Lkrms
 */
class SyncOperation
{
    /**
     * Add an entity to the connected system
     *
     * Typically corresponds to:
     * - `<provider>::create<entity_name>($entity)` or
     *   `<provider>::create_<entity_name>($entity)`
     * - `POST /<entity_name>`
     */
    public const CREATE = 0;

    /**
     * Read an entity from the connected system
     *
     * Typically corresponds to:
     * - `<provider>::get<entity_name>([$id])` or
     *   `<provider>::get_<entity_name>([$id])`
     * - `GET /<entity_name>/<id>` or `GET /<entity_name>`
     */
    public const READ = 1;

    /**
     * Update an entity in the connected system
     *
     * Typically corresponds to:
     * - `<provider>::update<entity_name>($entity)` or
     *   `<provider>::update_<entity_name>($entity)`
     * - `PUT /<entity_name>/<id>` or `PATCH /<entity_name>/<id>`
     */
    public const UPDATE = 2;

    /**
     * Delete an entity from the connected system
     *
     * Typically corresponds to:
     * - `<provider>::delete<entity_name>($entity)` or
     *   `<provider>::delete_<entity_name>($entity)`
     * - `DELETE /<entity_name>/<id>`
     */
    public const DELETE = 3;

    /**
     * Read a list of entities from the connected system
     *
     * Typically corresponds to:
     * - `<provider>::get<plural_entity_name>()` or
     *   `<provider>::getList_<entity_name>()`
     * - `GET /<entity_name>`
     */
    public const READ_LIST = 4;
}

