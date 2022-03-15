<?php

declare(strict_types=1);

namespace Lkrms\Sync;

use Lkrms\Ioc\Ioc;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use UnexpectedValueException;

/**
 * A generic wrapper for a SyncEntity CRUD implementation
 *
 * Or, to put it more simply, `SyncEntityProvider` allows this:
 *
 * ```php
 * $facultyProvider = new SyncEntityProvider(Faculty::class);
 * $facultyList     = $facultyProvider->get();
 * ```
 *
 * instead of this:
 *
 * ```php
 * $facultyProvider = Ioc::create(Faculty::class . "Provider");
 * $facultyList     = $facultyProvider->getFaculty();
 * ```
 *
 * When a new `SyncEntityProvider` is created, it is bound to the
 * {@see SyncProvider} currently servicing the given {@see SyncEntity}.
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
    private $SyncEntityShort;

    /**
     * @var SyncProvider
     */
    private $SyncProvider;

    private $Callbacks = [];

    public function __construct(string $name)
    {
        if (!is_subclass_of($name, SyncEntity::class))
        {
            throw new UnexpectedValueException("Not a subclass of SyncEntity: " . $name);
        }

        $parts                 = explode("\\", $name);
        $this->SyncEntity      = $name;
        $this->SyncEntityShort = end($parts);
        $this->SyncProvider    = Ioc::create($name . "Provider");
    }

    private function getProviderClass(): ReflectionClass
    {
        return new ReflectionClass($this->SyncProvider);
    }

    private function getProviderMethod(string $methodName): ?ReflectionMethod
    {
        $class = $this->getProviderClass();

        return $class->hasMethod($methodName)
            ? $class->getMethod($methodName)
            : null;
    }

    private function checkProviderMethod(
        string $methodName,
        bool $firstParamIsEntity,
        ?bool $firstParamAllowsNull
    ): bool
    {
        $methodName .= $this->SyncEntityShort;

        if (is_null($method = $this->getProviderMethod($methodName)))
        {
            return false;
        }

        if ($firstParamIsEntity || !is_null($firstParamAllowsNull))
        {
            if (is_null($param = $method->getParameters()[0] ?? null))
            {
                return false;
            }

            if (!is_null($firstParamAllowsNull) &&
                ($firstParamAllowsNull xor $param->allowsNull()))
            {
                return false;
            }

            if ($firstParamIsEntity &&
                (!(($type = $param->getType()) instanceof ReflectionNamedType) ||
                    $type->getName() != $this->SyncEntity))
            {
                return false;
            }
        }

        return true;
    }

    private function run(
        int $operation,
        string $methodName,
        bool $firstParamIsEntity,
        ?bool $firstParamAllowsNull,
        ...$params
    )
    {
        $method = $methodName . $this->SyncEntityShort;

        if (is_null($callback = $this->Callbacks[$operation] ?? null))
        {
            if ($this->checkProviderMethod($methodName, $firstParamIsEntity, $firstParamAllowsNull))
            {
                $callback = function (...$params) use ($method)
                {
                    return $this->SyncProvider->$method(...$params);
                };

                $this->Callbacks[$operation] = $callback;
            }
            else
            {
                throw new UnexpectedValueException("Invalid or missing method: " .
                    $this->SyncProvider::class . "::$method");
            }
        }

        if ($firstParamIsEntity && !is_a($params[0] ?? null, $this->SyncEntity))
        {
            throw new UnexpectedValueException("Not an instance of " .
                $this->SyncEntity . ': $param[0]');
        }

        return $callback(...$params);
    }

    /**
     * Adds an entity to the backend
     *
     * The underlying {@see SyncProvider} must implement the
     * {@see SyncOperation::CREATE} operation, e.g. for a `Faculty` entity:
     *
     * ```php
     * public function createFaculty(Faculty $entity);
     * ```
     *
     * @param SyncEntity $entity
     * @param mixed $params Additional parameters to pass to the
     * {@see SyncProvider}'s `create` method for the entity.
     */
    public function create(SyncEntity $entity, ...$params)
    {
        return $this->run(
            SyncOperation::CREATE,
            "create",
            true,
            false,
            $entity,
            ...$params
        );
    }

    public function get($id = null, ...$params)
    {
        if (is_null($id))
        {
            return $this->list(...$params);
        }

        return $this->run(
            SyncOperation::READ,
            "get",
            false,
            null,
            $id,
            ...$params
        );
    }

    public function update(SyncEntity $entity, ...$params)
    {
        return $this->run(
            SyncOperation::UPDATE,
            "update",
            true,
            false,
            $entity,
            ...$params
        );
    }

    public function delete(SyncEntity $entity, ...$params)
    {
        return $this->run(
            SyncOperation::DELETE,
            "delete",
            true,
            false,
            $entity,
            ...$params
        );
    }

    public function list(...$params)
    {
        $method = ["list", false, null];

        if (!$this->checkProviderMethod(...$method))
        {
            $method = ["get", false, true];
        }

        return $this->run(
            SyncOperation::LIST,
            ...$method,
            ...$params
        );
    }
}

