<?php

declare(strict_types=1);

namespace Lkrms\Sync;

use Lkrms\Convert;
use Lkrms\Ioc\Ioc;
use Lkrms\Reflect;
use ReflectionClass;
use ReflectionMethod;
use UnexpectedValueException;

/**
 * Provides a generic interface with a SyncEntity's current provider
 *
 * So you can do this:
 *
 * ```php
 * $genericProvider = new SyncEntityProvider(Faculty::class);
 * $faculties       = $genericProvider->get();
 * ```
 *
 * instead of this:
 *
 * ```php
 * $facultyProvider = Ioc::create(Faculty::class . "Provider");
 * $faculties       = $facultyProvider->getFaculty();
 * ```
 *
 * When a new `SyncEntityProvider` is created, it is bound to the
 * {@see SyncProvider} currently registered for the given {@see SyncEntity}.
 *
 * Registering a different provider for an entity has no effect on existing
 * `SyncEntityProvider` instances.
 *
 * @package Lkrms
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
        $this->SyncEntityPlural  = $name::getPlural();
        $this->SyncProvider      = Ioc::create($name . "Provider");
        $this->SyncProviderClass = new ReflectionClass($this->SyncProvider);
    }

    private function getProviderMethod(string $methodName): ?ReflectionMethod
    {
        $class = $this->SyncProviderClass;

        return $class->hasMethod($methodName)
            ? $class->getMethod($methodName)
            : null;
    }

    private function checkProviderMethod(
        string $method,
        string $altMethod,
        bool $entityParam,
        bool $idParam,
        bool $paramRequired
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

            if (($entityParam && $type != [$this->SyncEntity]) ||
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
        if (is_null($callback = $this->Callbacks[$operation] ?? null))
        {
            if ($providerMethod = $this->checkProviderMethod(
                $method,
                $altMethod,
                $entityParam,
                $idParam,
                $paramRequired
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

        if ($entityParam && !is_null($param) && !is_a($param, $this->SyncEntity))
        {
            throw new UnexpectedValueException("Not an instance of " . $this->SyncEntity . ': $param[0]');
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
     * Deletes an entity in the backend
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
     * Returns a list of entities from the backend
     *
     * The underlying {@see SyncProvider} must implement the
     * {@see SyncOperation::READ_LIST} operation, e.g. one of the following for
     * a `Faculty` entity:
     *
     * ```php
     * // 1. With a plural entity name
     * public function getFaculties(): array;
     *
     * // 2. With a singular name
     * public function getList_Faculty(): array;
     * ```
     *
     * @param mixed ...$params Parameters to pass to the provider.
     * @return SyncEntity[]
     */
    public function getList(...$params): array
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
}
