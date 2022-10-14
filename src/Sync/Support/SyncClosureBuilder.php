<?php

declare(strict_types=1);

namespace Lkrms\Sync\Support;

use Closure;
use Lkrms\Facade\Convert;
use Lkrms\Support\ClosureBuilder;
use Lkrms\Sync\Concept\SyncEntity;
use Lkrms\Sync\Contract\ISyncProvider;
use Lkrms\Sync\Support\SyncOperation;
use ReflectionClass;
use ReflectionMethod;
use RuntimeException;

final class SyncClosureBuilder extends ClosureBuilder
{
    /**
     * @var string
     */
    private $Class;

    /**
     * @var bool
     */
    private $IsEntity;

    /**
     * @var bool
     */
    private $IsProvider;

    /**
     * @var string|null
     */
    private $EntityNoun;

    /**
     * @var string|null
     */
    private $EntityPlural;

    /**
     * Lowercase method name => method name
     *
     * @var array<string,string>
     */
    private $SyncOperationMethods = [];

    /**
     * Lowercase "magic" method name => [ sync operation, entity ]
     *
     * @var array<string,array{0:int,1:string}>
     */
    private $SyncOperationsByMethod = [];

    /**
     * @var array<string,array<int,Closure|null>>
     */
    private $SyncOperationClosures = [];

    /**
     * @var array<string,Closure|null>
     */
    private $SyncOperationFromMethodClosures;

    protected function load(ReflectionClass $class): void
    {
        parent::load($class);

        $this->Class      = $class->name;
        $this->IsEntity   = $class->isSubclassOf(SyncEntity::class);
        $this->IsProvider = $class->implementsInterface(ISyncProvider::class);

        if ($this->IsEntity)
        {
            $this->EntityNoun = Convert::classToBasename($this->Class);
            $plural = $class->getMethod("getPluralClassName")->invoke(null);
            if (strcasecmp($this->EntityNoun, $plural))
            {
                $this->EntityPlural = $plural;
            }
        }

        if ($this->IsProvider)
        {
            foreach ($class->getMethods(ReflectionMethod::IS_PUBLIC) as $method)
            {
                if (!$method->isStatic() &&
                    preg_match('/^(?:create|get|update|delete)(?:List_|_)?(?!List_|_).+/i',
                        $method->name) &&
                    !in_array(strtolower($method->name),
                        ["getbackendhash", "getdateformatter"]))
                {
                    $this->SyncOperationMethods[strtolower($method->name)] = $method->name;
                }
            }

            foreach ($class->getMethod("getBindable")->invoke(null) as $interface)
            {
                $pos = strrpos(strtolower($interface), "provider");
                if ($pos === false || $pos < 1 ||
                    !is_a($entity = substr($interface, 0, $pos), SyncEntity::class, true))
                {
                    continue;
                }

                $entity = static::get($entity);
                [$noun, $plural] = [strtolower($entity->EntityNoun), strtolower($entity->EntityPlural)];

                if ($plural)
                {
                    $this->addProvides($entity, SyncOperation::CREATE_LIST, "create" . $plural);
                    $this->addProvides($entity, SyncOperation::READ_LIST, "get" . $plural);
                    $this->addProvides($entity, SyncOperation::UPDATE_LIST, "update" . $plural);
                    $this->addProvides($entity, SyncOperation::DELETE_LIST, "delete" . $plural);
                }

                $this->addProvides($entity, SyncOperation::CREATE, "create" . $noun);
                $this->addProvides($entity, SyncOperation::CREATE, "create_" . $noun);
                $this->addProvides($entity, SyncOperation::READ, "get" . $noun);
                $this->addProvides($entity, SyncOperation::READ, "get_" . $noun);
                $this->addProvides($entity, SyncOperation::UPDATE, "update" . $noun);
                $this->addProvides($entity, SyncOperation::UPDATE, "update_" . $noun);
                $this->addProvides($entity, SyncOperation::DELETE, "delete" . $noun);
                $this->addProvides($entity, SyncOperation::DELETE, "delete_" . $noun);
                $this->addProvides($entity, SyncOperation::CREATE_LIST, "createlist_" . $noun);
                $this->addProvides($entity, SyncOperation::READ_LIST, "getlist_" . $noun);
                $this->addProvides($entity, SyncOperation::UPDATE_LIST, "updatelist_" . $noun);
                $this->addProvides($entity, SyncOperation::DELETE_LIST, "deletelist_" . $noun);
            }

            $this->SyncOperationsByMethod = array_filter($this->SyncOperationsByMethod);
        }
    }

    private function addProvides(SyncClosureBuilder $entity, int $operation, string $method): void
    {
        if (array_key_exists($method, $this->SyncOperationsByMethod) ||
            array_key_exists($method, $this->SyncOperationMethods))
        {
            $this->SyncOperationsByMethod[$method] = null;

            return;
        }

        $this->SyncOperationsByMethod[$method] = [$operation, $entity->Class];
    }

    /**
     * Get the SyncProvider method that implements a SyncOperation for an entity
     *
     * Returns `null` if:
     * - the closure builder was not created for an {@see ISyncProvider},
     * - `$entityClosureBuilder` was not created for a {@see SyncEntity}
     *   subclass, or
     * - the {@see ISyncProvider} class doesn't implement the given
     *   {@see SyncOperation} via a method
     *
     * @param int $operation A {@see SyncOperation} value.
     * @param string|SyncClosureBuilder $entity
     */
    final public function getSyncOperationClosure(int $operation, $entity, ISyncProvider $provider): ?Closure
    {
        if (!($entity instanceof SyncClosureBuilder))
        {
            $entity = static::get($entity);
        }

        if (!$this->IsProvider || !$entity->IsEntity)
        {
            return null;
        }

        if (($closure = $this->SyncOperationClosures[$entity->Class][$operation] ?? false) === false)
        {
            if ($method = $this->getSyncOperationMethod($operation, $entity))
            {
                $closure = fn(...$args) => $this->$method(...$args);
            }

            $this->SyncOperationClosures[$entity->Class][$operation] = $closure ?: null;
        }

        return $closure ? $closure->bindTo($provider) : null;
    }

    /**
     * Get a closure to perform sync operations on behalf of a provider's
     * "magic" method
     *
     * Returns `null` if:
     * - the closure builder was not created for an {@see ISyncProvider},
     * - the {@see ISyncProvider} class has already has `$method`, or
     * - `$method` doesn't resolve to an unambiguous sync operation on a
     *   {@see SyncEntity} subclass serviced by the {@see ISyncProvider} class
     *
     * @return Closure|null
     * ```php
     * fn(SyncContext $ctx, ...$args)
     * ```
     */
    final public function getSyncOperationFromMethodClosure(string $method, ISyncProvider $provider): ?Closure
    {
        if (!$this->IsProvider)
        {
            return null;
        }

        if (($closure = $this->SyncOperationFromMethodClosures[$method = strtolower($method)] ?? false) === false)
        {
            if ($operation = $this->SyncOperationsByMethod[$method] ?? null)
            {
                [$operation, $entity] = $operation;

                $closure =
                    function (SyncContext $ctx, ...$args) use ($entity, $operation)
                    {
                        /** @var ISyncProvider $this */
                        return $this->with($entity, $ctx)->run($operation, ...$args);
                    };
            }

            $this->SyncOperationFromMethodClosures[$method] = $closure ?: null;
        }

        return $closure ? $closure->bindTo($provider) : null;
    }

    private function getSyncOperationMethod(int $operation, SyncClosureBuilder $entity): ?string
    {
        [$noun, $plural] = [$entity->EntityNoun, $entity->EntityPlural];

        if ($plural)
        {
            switch ($operation)
            {
                case SyncOperation::CREATE_LIST:
                    $methods[] = "create" . $plural;
                    break;

                case SyncOperation::READ_LIST:
                    $methods[] = "get" . $plural;
                    break;

                case SyncOperation::UPDATE_LIST:
                    $methods[] = "update" . $plural;
                    break;

                case SyncOperation::DELETE_LIST:
                    $methods[] = "delete" . $plural;
                    break;
            }
        }

        switch ($operation)
        {
            case SyncOperation::CREATE:
                $methods[] = "create" . $noun;
                $methods[] = "create_" . $noun;
                break;

            case SyncOperation::READ:
                $methods[] = "get" . $noun;
                $methods[] = "get_" . $noun;
                break;

            case SyncOperation::UPDATE:
                $methods[] = "update" . $noun;
                $methods[] = "update_" . $noun;
                break;

            case SyncOperation::DELETE:
                $methods[] = "delete" . $noun;
                $methods[] = "delete_" . $noun;
                break;

            case SyncOperation::CREATE_LIST:
                $methods[] = "createList_" . $noun;
                break;

            case SyncOperation::READ_LIST:
                $methods[] = "getList_" . $noun;
                break;

            case SyncOperation::UPDATE_LIST:
                $methods[] = "updateList_" . $noun;
                break;

            case SyncOperation::DELETE_LIST:
                $methods[] = "deleteList_" . $noun;
                break;
        }

        $methods = array_intersect_key(
            $this->SyncOperationMethods,
            array_flip(array_map(fn(string $method) => strtolower($method), $methods ?? []))
        );

        if (count($methods) > 1)
        {
            throw new RuntimeException("Too many implementations: " . implode(", ", $methods));
        }

        return reset($methods) ?: null;
    }

}
