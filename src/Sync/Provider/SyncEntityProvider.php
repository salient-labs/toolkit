<?php

declare(strict_types=1);

namespace Lkrms\Sync\Provider;

use Lkrms\Facade\Convert;
use Lkrms\Facade\DI;
use Lkrms\Facade\Reflect;
use Lkrms\Sync\Concept\SyncProvider;
use Lkrms\Sync\SyncEntity;
use Lkrms\Sync\SyncOperation;
use ReflectionClass;
use ReflectionMethod;
use UnexpectedValueException;

/**
 * Provides an entity-agnostic interface with a SyncEntity's current provider
 *
 * So you can do this:
 *
 * ```php
 * $faculties = Faculty::backend()->getList();
 * ```
 *
 * or this:
 *
 * ```php
 * $genericProvider = new SyncEntityProvider(Faculty::class);
 * $faculties       = $genericProvider->getList();
 * ```
 *
 * instead of this:
 *
 * ```php
 * $facultyProvider = DI::get(Faculty::class . "Provider");
 * $faculties       = $facultyProvider->getFaculties();
 * ```
 *
 * When a new `SyncEntityProvider` is created, it is bound to the
 * {@see SyncProvider} currently registered for the given {@see SyncEntity}.
 *
 * Registering a different provider for an entity has no effect on existing
 * `SyncEntityProvider` instances.
 *
 */
class SyncEntityProvider
{
    /**
     * @var string
     */
    private $SyncEntity;

    /**
     * @var string
     */
    private $SyncEntityNoun;

    /**
     * @var string
     */
    private $SyncEntityPlural;

    /**
     * @var SyncProvider
     */
    private $SyncProvider;

    /**
     * @var ReflectionClass
     */
    private $SyncProviderClass;

    private $Callbacks = [];

    public function __construct(string $name)
    {
        if (!is_subclass_of($name, SyncEntity::class))
        {
            throw new UnexpectedValueException("Not a subclass of SyncEntity: " . $name);
        }

        $this->SyncEntity        = $name;
        $this->SyncEntityNoun    = Convert::classToBasename($name);
        $this->SyncEntityPlural  = $name::getPluralClassName();
        $this->SyncProvider      = DI::get($name . "Provider");
        $this->SyncProviderClass = new ReflectionClass($this->SyncProvider);
    }

    private function getProviderMethod(string $methodName): ?ReflectionMethod
    {
        return $this->SyncProviderClass->hasMethod($methodName)
            ? $this->SyncProviderClass->getMethod($methodName)
            : null;
    }

    private function checkProviderMethod(
        string $method,
        string $altMethod,
        bool $entityParam,
        bool $idParam,
        bool $paramRequired,
        string $entityParamType
    ): ?string
    {
        if (is_null($method = $this->getProviderMethod($method)) &&
            is_null($method = $this->getProviderMethod($altMethod)))
        {
            return null;
        }

        $required = 0;

        if ($entityParam || $idParam)
        {
            $required = 1;

            if (is_null($param = $method->getParameters()[0] ?? null))
            {
                return null;
            }

            if ($paramRequired && $param->allowsNull())
            {
                return null;
            }

            $type = Reflect::getAllTypeNames($param->getType());

            if (($entityParam && $type != [$entityParamType]) ||
                ($idParam && !empty(array_diff($type, ["int", "string"]))))
            {
                return null;
            }
        }

        if ($method->getNumberOfRequiredParameters() != $required)
        {
            return null;
        }

        return $method->getName();
    }

    private function run(
        int $operation,
        string $method,
        string $altMethod,
        bool $entityParam,
        bool $idParam,
        bool $paramRequired,
        ...$params
    ) {
        $isList = SyncOperation::isList($operation);

        if (is_null($callback = $this->Callbacks[$operation] ?? null))
        {
            if ($providerMethod = $this->checkProviderMethod(
                $method,
                $altMethod,
                $entityParam,
                $idParam,
                $paramRequired,
                $isList ? "array" : $this->SyncEntity
            ))
            {
                $callback = function (...$params) use ($providerMethod)
                {
                    return $this->SyncProvider->$providerMethod(...$params);
                };

                $this->Callbacks[$operation] = $callback;
            }
            else
            {
                throw new UnexpectedValueException("Invalid or missing method: " .
                    get_class($this->SyncProvider) . "::$method");
            }
        }

        $param = $params[0] ?? null;

        if ($entityParam && !is_null($param) && !($isList ? is_array($param) : is_a($param, $this->SyncEntity)))
        {
            throw new UnexpectedValueException($this->SyncEntity . ($isList ? "[]" : "") . ' required: $param[0]');
        }
        elseif ($idParam && !is_null($param) && !is_int($param) && !is_string($param))
        {
            throw new UnexpectedValueException('Not an identifier: $param[0]');
        }
        elseif ($paramRequired && is_null($param))
        {
            throw new UnexpectedValueException('Value required: $param[0]');
        }

        return $callback(...$params);
    }

    /**
     * Adds an entity to the backend
     *
     * The underlying {@see SyncProvider} must implement the
     * {@see SyncOperation::CREATE} operation, e.g. one of the following for a
     * `Faculty` entity:
     *
     * ```php
     * // 1.
     * public function createFaculty(Faculty $entity): Faculty;
     *
     * // 2.
     * public function create_Faculty(Faculty $entity): Faculty;
     * ```
     *
     * The first parameter:
     * - MUST be defined
     * - MUST have a type declaration, which MUST be the class of the entity
     *   being created
     * - MUST be required
     *
     * @param SyncEntity $entity
     * @param mixed ...$params Additional parameters to pass to the provider.
     * @return SyncEntity
     */
    public function create(SyncEntity $entity, ...$params): SyncEntity
    {
        return $this->run(
            SyncOperation::CREATE,
            "create" . $this->SyncEntityNoun,
            "create_" . $this->SyncEntityNoun,
            true,
            false,
            true,
            $entity,
            ...$params
        );
    }

    /**
     * Returns an entity from the backend
     *
     * The underlying {@see SyncProvider} must implement the
     * {@see SyncOperation::READ} operation, e.g. one of the following for a
     * `Faculty` entity:
     *
     * ```php
     * // 1.
     * public function getFaculty(int $id = null): Faculty;
     *
     * // 2.
     * public function get_Faculty(int $id = null): Faculty;
     * ```
     *
     * The first parameter:
     * - MUST be defined
     * - MAY have a type declaration, which MUST be one of `int`, `string`, or
     *   `int|string` if included
     * - MAY be nullable
     *
     * @param int|string|null $id
     * @param mixed ...$params Additional parameters to pass to the provider.
     * @return SyncEntity
     */
    public function get($id = null, ...$params): SyncEntity
    {
        return $this->run(
            SyncOperation::READ,
            "get" . $this->SyncEntityNoun,
            "get_" . $this->SyncEntityNoun,
            false,
            true,
            false,
            $id,
            ...$params
        );
    }

    /**
     * Updates an entity in the backend
     *
     * The underlying {@see SyncProvider} must implement the
     * {@see SyncOperation::UPDATE} operation, e.g. one of the following for a
     * `Faculty` entity:
     *
     * ```php
     * // 1.
     * public function updateFaculty(Faculty $entity): Faculty;
     *
     * // 2.
     * public function update_Faculty(Faculty $entity): Faculty;
     * ```
     *
     * The first parameter:
     * - MUST be defined
     * - MUST have a type declaration, which MUST be the class of the entity
     *   being updated
     * - MUST be required
     *
     * @param SyncEntity $entity
     * @param mixed ...$params Additional parameters to pass to the provider.
     * @return SyncEntity
     */
    public function update(SyncEntity $entity, ...$params): SyncEntity
    {
        return $this->run(
            SyncOperation::UPDATE,
            "update" . $this->SyncEntityNoun,
            "update_" . $this->SyncEntityNoun,
            true,
            false,
            true,
            $entity,
            ...$params
        );
    }

    /**
     * Deletes an entity from the backend
     *
     * The underlying {@see SyncProvider} must implement the
     * {@see SyncOperation::DELETE} operation, e.g. one of the following for a
     * `Faculty` entity:
     *
     * ```php
     * // 1.
     * public function deleteFaculty(Faculty $entity): Faculty;
     *
     * // 2.
     * public function delete_Faculty(Faculty $entity): Faculty;
     * ```
     *
     * The first parameter:
     * - MUST be defined
     * - MUST have a type declaration, which MUST be the class of the entity
     *   being deleted
     * - MUST be required
     *
     * The return value:
     * - SHOULD represent the final state of the entity before it was deleted
     * - MAY be `null`
     *
     * @param SyncEntity $entity
     * @param mixed ...$params Additional parameters to pass to the provider.
     * @return null|SyncEntity
     */
    public function delete(SyncEntity $entity, ...$params): ?SyncEntity
    {
        return $this->run(
            SyncOperation::DELETE,
            "delete" . $this->SyncEntityNoun,
            "delete_" . $this->SyncEntityNoun,
            true,
            false,
            true,
            $entity,
            ...$params
        );
    }

    /**
     * Adds a list of entities to the backend
     *
     * The underlying {@see SyncProvider} must implement the
     * {@see SyncOperation::CREATE_LIST} operation, e.g. one of the following
     * for a `Faculty` entity:
     *
     * ```php
     * // 1. With a plural entity name
     * public function createFaculties(iterable $entities): iterable;
     *
     * // 2. With a singular name
     * public function createList_Faculty(iterable $entities): iterable;
     * ```
     *
     * The first parameter:
     * - MUST be defined
     * - MUST have a type declaration, which MUST be `array`
     * - MUST be required
     *
     * @param iterable<SyncEntity> $entities
     * @param mixed ...$params Additional parameters to pass to the provider.
     * @return iterable<SyncEntity>
     */
    public function createList(iterable $entities, ...$params): iterable
    {
        return $this->run(
            SyncOperation::CREATE_LIST,
            "create" . $this->SyncEntityPlural,
            "createList_" . $this->SyncEntityNoun,
            true,
            false,
            true,
            $entities,
            ...$params
        );
    }

    /**
     * Returns a list of entities from the backend
     *
     * The underlying {@see SyncProvider} must implement the
     * {@see SyncOperation::READ_LIST} operation, e.g. one of the following for
     * a `Faculty` entity:
     *
     * ```php
     * // 1. With a plural entity name
     * public function getFaculties(): iterable;
     *
     * // 2. With a singular name
     * public function getList_Faculty(): iterable;
     * ```
     *
     * @param mixed ...$params Parameters to pass to the provider.
     * @return iterable<SyncEntity>
     */
    public function getList(...$params): iterable
    {
        return $this->run(
            SyncOperation::READ_LIST,
            "get" . $this->SyncEntityPlural,
            "getList_" . $this->SyncEntityNoun,
            false,
            false,
            false,
            ...$params
        );
    }

    /**
     * Updates a list of entities in the backend
     *
     * The underlying {@see SyncProvider} must implement the
     * {@see SyncOperation::UPDATE_LIST} operation, e.g. one of the following
     * for a `Faculty` entity:
     *
     * ```php
     * // 1. With a plural entity name
     * public function updateFaculties(iterable $entities): iterable;
     *
     * // 2. With a singular name
     * public function updateList_Faculty(iterable $entities): iterable;
     * ```
     *
     * The first parameter:
     * - MUST be defined
     * - MUST have a type declaration, which MUST be `array`
     * - MUST be required
     *
     * @param iterable<SyncEntity> $entities
     * @param mixed ...$params Additional parameters to pass to the provider.
     * @return iterable<SyncEntity>
     */
    public function updateList(iterable $entities, ...$params): iterable
    {
        return $this->run(
            SyncOperation::UPDATE_LIST,
            "update" . $this->SyncEntityPlural,
            "updateList_" . $this->SyncEntityNoun,
            true,
            false,
            true,
            $entities,
            ...$params
        );
    }

    /**
     * Deletes a list of entities from the backend
     *
     * The underlying {@see SyncProvider} must implement the
     * {@see SyncOperation::DELETE_LIST} operation, e.g. one of the following
     * for a `Faculty` entity:
     *
     * ```php
     * // 1. With a plural entity name
     * public function deleteFaculties(iterable $entities): ?iterable;
     *
     * // 2. With a singular name
     * public function deleteList_Faculty(iterable $entities): ?iterable;
     * ```
     *
     * The first parameter:
     * - MUST be defined
     * - MUST have a type declaration, which MUST be `array`
     * - MUST be required
     *
     * The return value:
     * - SHOULD represent the final state of the entities before they were
     *   deleted
     * - MAY be `null`
     *
     * @param iterable<SyncEntity> $entities
     * @param mixed ...$params Additional parameters to pass to the provider.
     * @return null|iterable<SyncEntity>
     */
    public function deleteList(iterable $entities, ...$params): ?iterable
    {
        return $this->run(
            SyncOperation::DELETE_LIST,
            "delete" . $this->SyncEntityPlural,
            "deleteList_" . $this->SyncEntityNoun,
            true,
            false,
            true,
            $entities,
            ...$params
        );
    }
}
