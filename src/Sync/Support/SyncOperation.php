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
     * - `<provider>::create<entity_name>(<entity_class> $entity)`
     * - `<provider>::create_<entity_name>(<entity_class> $entity)`
     * - `POST /<entity_name>`
     * - `INSERT INTO <entity_name> ...`
     */
    public const CREATE = 0;

    /**
     * Read an entity from the backend
     *
     * Typically corresponds to:
     * - `<provider>::get<entity_name>(int|string|null $id)`
     * - `<provider>::get_<entity_name>(int|string|null $id)`
     * - `GET /<entity_name>/<id>`
     * - `GET /<entity_name>`
     * - `SELECT ... FROM <entity_name> WHERE <id_field> = <id>`
     */
    public const READ = 1;

    /**
     * Update an entity in the backend
     *
     * Typically corresponds to:
     * - `<provider>::update<entity_name>(<entity_class> $entity)`
     * - `<provider>::update_<entity_name>(<entity_class> $entity)`
     * - `PUT /<entity_name>/<id>`
     * - `PATCH /<entity_name>/<id>`
     * - `UPDATE <entity_name> WHERE ...`
     */
    public const UPDATE = 2;

    /**
     * Delete an entity from the backend
     *
     * Typically corresponds to:
     * - `<provider>::delete<entity_name>(<entity_class> $entity)`
     * - `<provider>::delete_<entity_name>(<entity_class> $entity)`
     * - `DELETE /<entity_name>/<id>`
     * - `DELETE FROM <entity_name> WHERE ...`
     */
    public const DELETE = 3;

    /**
     * Add a list of entities to the backend
     *
     * Typically corresponds to:
     * - `<provider>::create<plural_entity_name>(<entity_class>[] $entities)`
     * - `<provider>::createList_<entity_name>(<entity_class>[] $entities)`
     * - `POST /<entity_name>`
     * - `INSERT INTO <entity_name> ...`
     */
    public const CREATE_LIST = 4;

    /**
     * Read a list of entities from the backend
     *
     * Typically corresponds to:
     * - `<provider>::get<plural_entity_name>()`
     * - `<provider>::getList_<entity_name>()`
     * - `GET /<entity_name>`
     * - `SELECT ... FROM <entity_name>`
     */
    public const READ_LIST = 5;

    /**
     * Update a list of entities in the backend
     *
     * Typically corresponds to:
     * - `<provider>::update<plural_entity_name>(<entity_class>[] $entity)`
     * - `<provider>::updateList_<entity_name>(<entity_class>[] $entity)`
     * - `PUT /<entity_name>`
     * - `PATCH /<entity_name>`
     * - `UPDATE <entity_name> WHERE ...`
     */
    public const UPDATE_LIST = 6;

    /**
     * Delete a list of entities from the backend
     *
     * Typically corresponds to:
     * - `<provider>::delete<plural_entity_name>(<entity_class>[] $entity)`
     * - `<provider>::deleteList_<entity_name>(<entity_class>[] $entity)`
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
     * @psalm-return (SyncOperation::*)[]
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
     * @psalm-param SyncOperation::* $operation
     */
    public static function isList(int $operation): bool
    {
        return (bool) ($operation & 4);
    }
}
