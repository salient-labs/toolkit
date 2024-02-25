<?php declare(strict_types=1);

namespace Salient\Sync\Catalog;

use Salient\Core\AbstractConvertibleEnumeration;

/**
 * Sync operation types
 *
 * @extends AbstractConvertibleEnumeration<int>
 */
final class SyncOperation extends AbstractConvertibleEnumeration
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

    protected static $NameMap = [
        self::CREATE => 'CREATE',
        self::READ => 'READ',
        self::UPDATE => 'UPDATE',
        self::DELETE => 'DELETE',
        self::CREATE_LIST => 'CREATE_LIST',
        self::READ_LIST => 'READ_LIST',
        self::UPDATE_LIST => 'UPDATE_LIST',
        self::DELETE_LIST => 'DELETE_LIST',
    ];

    protected static $ValueMap = [
        'CREATE' => self::CREATE,
        'READ' => self::READ,
        'UPDATE' => self::UPDATE,
        'DELETE' => self::DELETE,
        'CREATE_LIST' => self::CREATE_LIST,
        'READ_LIST' => self::READ_LIST,
        'UPDATE_LIST' => self::UPDATE_LIST,
        'DELETE_LIST' => self::DELETE_LIST,
    ];

    /**
     * True if an operation is CREATE_LIST, READ_LIST, UPDATE_LIST or
     * DELETE_LIST
     *
     * @param SyncOperation::* $operation
     * @return ($operation is SyncOperation::*_LIST ? true : false)
     */
    public static function isList($operation): bool
    {
        return in_array($operation, SyncOperations::ALL_LIST, true);
    }

    /**
     * True if an operation is READ or READ_LIST
     *
     * @param SyncOperation::* $operation
     * @return ($operation is SyncOperation::READ* ? true : false)
     */
    public static function isRead($operation): bool
    {
        return in_array($operation, SyncOperations::ALL_READ, true);
    }

    /**
     * True if an operation is CREATE, UPDATE, DELETE, CREATE_LIST, UPDATE_LIST
     * or DELETE_LIST
     *
     * @param SyncOperation::* $operation
     * @return ($operation is SyncOperation::READ* ? false : true)
     */
    public static function isWrite($operation): bool
    {
        return in_array($operation, SyncOperations::ALL_WRITE, true);
    }
}
