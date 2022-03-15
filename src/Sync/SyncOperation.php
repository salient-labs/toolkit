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
     * - `<provider>::create<entity_name>($entity)`
     * - HTTP `POST /<entity_name>`
     */
    public const CREATE = 0;

    /**
     * Read an entity from the connected system
     *
     * Typically corresponds to:
     * - `<provider>::get<entity_name>($id)`
     * - HTTP `GET /<entity_name>/<id>`
     */
    public const READ = 1;

    /**
     * Update an entity in the connected system
     *
     * Typically corresponds to:
     * - `<provider>::update<entity_name>($entity)`
     * - HTTP `PUT /<entity_name>/<id>`
     */
    public const UPDATE = 2;

    /**
     * Delete an entity from the connected system
     *
     * Typically corresponds to:
     * - `<provider>::delete<entity_name>($entity)`
     * - HTTP `DELETE /<entity_name>/<id>`
     */
    public const DELETE = 3;

    /**
     * Read multiple entities from the connected system
     *
     * Typically corresponds to:
     * - `<provider>::get<entity_name>()` or
     *   `<provider>::list<entity_name>()`
     * - HTTP `GET /<entity_name>`
     */
    public const LIST = 4;
}

