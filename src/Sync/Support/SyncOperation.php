<?php declare(strict_types=1);

namespace Lkrms\Sync\Support;

use Lkrms\Concept\Enumeration;
use Lkrms\Concern\IsConvertibleEnumeration;
use Lkrms\Contract\IConvertibleEnumeration;
use UnexpectedValueException;

/**
 * Sync operation types
 *
 */
final class SyncOperation extends Enumeration implements IConvertibleEnumeration
{
    use IsConvertibleEnumeration;

    /**
     * Add an entity to the backend
     *
     * Typically corresponds to:
     * - `POST /<entity_name>`
     * - `INSERT INTO <entity_name> ...`
     */
    public const CREATE = 0;

    /**
     * Get an entity from the backend
     *
     * Typically corresponds to:
     * - `GET /<entity_name>/<id>`
     * - `GET /<entity_name>`
     * - `SELECT ... FROM <entity_name> WHERE <id_field> = <id>`
     */
    public const READ = 1;

    /**
     * Update an entity in the backend
     *
     * Typically corresponds to:
     * - `PUT /<entity_name>/<id>`
     * - `PATCH /<entity_name>/<id>`
     * - `UPDATE <entity_name> WHERE ...`
     */
    public const UPDATE = 2;

    /**
     * Delete an entity from the backend
     *
     * Typically corresponds to:
     * - `DELETE /<entity_name>/<id>`
     * - `DELETE FROM <entity_name> WHERE ...`
     */
    public const DELETE = 3;

    /**
     * Add a list of entities to the backend
     *
     * Typically corresponds to:
     * - `POST /<entity_name>`
     * - `INSERT INTO <entity_name> ...`
     */
    public const CREATE_LIST = 4;

    /**
     * Get a list of entities from the backend
     *
     * Typically corresponds to:
     * - `GET /<entity_name>`
     * - `SELECT ... FROM <entity_name>`
     */
    public const READ_LIST = 5;

    /**
     * Update a list of entities in the backend
     *
     * Typically corresponds to:
     * - `PUT /<entity_name>`
     * - `PATCH /<entity_name>`
     * - `UPDATE <entity_name> WHERE ...`
     */
    public const UPDATE_LIST = 6;

    /**
     * Delete a list of entities from the backend
     *
     * Typically corresponds to:
     * - `DELETE /<entity_name>`
     * - `DELETE FROM <entity_name> WHERE ...`
     */
    public const DELETE_LIST = 7;

    protected static function getNameMap(): array
    {
        return [
            self::CREATE      => 'CREATE',
            self::READ        => 'READ',
            self::UPDATE      => 'UPDATE',
            self::DELETE      => 'DELETE',
            self::CREATE_LIST => 'CREATE_LIST',
            self::READ_LIST   => 'READ_LIST',
            self::UPDATE_LIST => 'UPDATE_LIST',
            self::DELETE_LIST => 'DELETE_LIST',
        ];
    }

    protected static function getValueMap(): array
    {
        return [
            'create'      => self::CREATE,
            'read'        => self::READ,
            'update'      => self::UPDATE,
            'delete'      => self::DELETE,
            'create_list' => self::CREATE_LIST,
            'read_list'   => self::READ_LIST,
            'update_list' => self::UPDATE_LIST,
            'delete_list' => self::DELETE_LIST,
        ];
    }

    /**
     * Get a list of all operations
     *
     * @phpstan-return (SyncOperation::*)[]
     */
    public static function getAll(): array
    {
        return [
            self::CREATE,
            self::READ,
            self::UPDATE,
            self::DELETE,
            self::CREATE_LIST,
            self::READ_LIST,
            self::UPDATE_LIST,
            self::DELETE_LIST,
        ];
    }

    /**
     * True if an operation is CREATE_LIST, READ_LIST, UPDATE_LIST or
     * DELETE_LIST
     *
     * @phpstan-param SyncOperation::* $operation
     * @phpstan-return ($operation is SyncOperation::*_LIST ? true : false)
     */
    public static function isList(int $operation): bool
    {
        return (bool) ($operation & 4);
    }
}
