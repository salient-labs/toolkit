<?php

declare(strict_types=1);

namespace Lkrms\Sync\Support;

use Closure;
use Lkrms\Facade\Convert;
use Lkrms\Support\ClosureBuilder;
use Lkrms\Support\Dictionary\Regex;
use Lkrms\Sync\Concept\SyncEntity;
use Lkrms\Sync\Concept\SyncProvider;
use Lkrms\Sync\Contract\ISyncProvider;
use Lkrms\Sync\Support\SyncOperation;
use ReflectionClass;
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
     * Not set if the plural class name is the same the singular one
     *
     * @var string|null
     */
    private $EntityPlural;

    /**
     * Interfaces that extend ISyncProvider
     *
     * @var string[]
     */
    private $SyncProviderInterfaces = [];

    /**
     * Entities serviced by ISyncProvider interfaces
     *
     * @var string[]
     */
    private $SyncProviderEntities = [];

    /**
     * Unambiguous lowercase entity basename => entity
     *
     * @var array<string,string>
     */
    private $SyncProviderEntityBasenames = [];

    /**
     * Lowercase method name => sync operation method declared by the provider
     *
     * @var array<string,string>
     */
    private $SyncOperationMethods = [];

    /**
     * Lowercase "magic" sync operation method => [ sync operation, entity ]
     *
     * Used only to map "magic" method names to sync operations. Providers
     * aren't required to service any of them.
     *
     * @var array<string,array{0:int,1:string}>
     */
    private $SyncOperationMagicMethods = [];

    /**
     * Entity => sync operation => closure
     *
     * @var array<string,array<int,Closure|null>>
     */
    private $DeclaredSyncOperationClosures = [];

    /**
     * Lowercase "magic" sync operation method => closure
     *
     * @var array<string,Closure|null>
     */
    private $MagicSyncOperationClosures = [];

    final public static function entityToProvider(string $entity): string
    {
        return sprintf('%s\\Provider\\%sProvider', Convert::classToNamespace($entity), Convert::classToBasename($entity));
    }

    final public static function providerToEntity(string $provider): ?string
    {
        if (preg_match('/^(?P<namespace>' . Regex::PHP_TYPE . '\\\\)?Provider\\\\(?P<class>' . Regex::PHP_IDENTIFIER . ')?Provider$/U', $provider, $matches))
        {
            return $matches['namespace'] . $matches['class'];
        }

        return null;
    }

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
            /** @todo Build out ISyncProvider and use it here instead */
            $baseProvider = new ReflectionClass(SyncProvider::class);

            foreach ($class->getInterfaces() as $name => $interface)
            {
                // Add ISyncProvider interfaces to SyncProviderInterfaces
                if (!$interface->isSubclassOf(ISyncProvider::class))
                {
                    continue;
                }
                $this->SyncProviderInterfaces[] = $name;

                // Add the entities they service to SyncProviderEntities
                if (!($entity = self::providerToEntity($name)) ||
                    !is_a($entity, SyncEntity::class, true))
                {
                    continue;
                }
                $entity = static::get($entity);
                $this->SyncProviderEntities[] = $entity->Class;

                // Map unambiguous lowercase entity basenames to qualified names
                // in SyncProviderEntityBasenames
                $basename = strtolower(Convert::classToBasename($entity->Class));
                $this->SyncProviderEntityBasenames[$basename] = (
                    array_key_exists($basename, $this->SyncProviderEntityBasenames) ? null : $entity->Class
                );

                $fn = function (int $operation, string $method) use ($class, $baseProvider, $entity)
                {
                    // If $method has already been processed, the entity it
                    // services is ambiguous and it can't be used
                    if (array_key_exists($method, $this->SyncOperationMethods) ||
                        array_key_exists($method, $this->SyncOperationMagicMethods))
                    {
                        $this->SyncOperationMagicMethods[$method] = $this->SyncOperationMethods[$method] = null;

                        return;
                    }
                    if ($class->hasMethod($method) && ($_method = $class->getMethod($method))->isPublic())
                    {
                        if ($_method->isStatic()
                            || ($baseProvider->hasMethod($method) && !$baseProvider->getMethod($method)->isPrivate()))
                        {
                            $this->SyncOperationMethods[$method] = null;

                            return;
                        }
                        $this->SyncOperationMethods[$method] = $_method->name;

                        return;
                    }
                    $this->SyncOperationMagicMethods[$method] = [$operation, $entity->Class];
                };

                [$noun, $plural] = [strtolower($entity->EntityNoun), strtolower($entity->EntityPlural)];

                if ($plural)
                {
                    $fn(SyncOperation::CREATE_LIST, "create" . $plural);
                    $fn(SyncOperation::READ_LIST, "get" . $plural);
                    $fn(SyncOperation::UPDATE_LIST, "update" . $plural);
                    $fn(SyncOperation::DELETE_LIST, "delete" . $plural);
                }

                $fn(SyncOperation::CREATE, "create" . $noun);
                $fn(SyncOperation::CREATE, "create_" . $noun);
                $fn(SyncOperation::READ, "get" . $noun);
                $fn(SyncOperation::READ, "get_" . $noun);
                $fn(SyncOperation::UPDATE, "update" . $noun);
                $fn(SyncOperation::UPDATE, "update_" . $noun);
                $fn(SyncOperation::DELETE, "delete" . $noun);
                $fn(SyncOperation::DELETE, "delete_" . $noun);
                $fn(SyncOperation::CREATE_LIST, "createlist_" . $noun);
                $fn(SyncOperation::READ_LIST, "getlist_" . $noun);
                $fn(SyncOperation::UPDATE_LIST, "updatelist_" . $noun);
                $fn(SyncOperation::DELETE_LIST, "deletelist_" . $noun);
            }

            $this->SyncProviderEntityBasenames = array_filter($this->SyncProviderEntityBasenames);
            $this->SyncOperationMethods        = array_filter($this->SyncOperationMethods);
            $this->SyncOperationMagicMethods   = array_filter($this->SyncOperationMagicMethods);
        }
    }

    /**
     * Get a list of ISyncProvider interfaces implemented by the provider
     *
     * @return string[]|null
     */
    final public function getSyncProviderInterfaces(): ?array
    {
        if (!$this->IsProvider)
        {
            return null;
        }

        return $this->SyncProviderInterfaces;
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
    final public function getDeclaredSyncOperationClosure(int $operation, $entity, ISyncProvider $provider): ?Closure
    {
        if (!($entity instanceof SyncClosureBuilder))
        {
            $entity = static::get($entity);
        }

        if (!$this->IsProvider || !$entity->IsEntity)
        {
            return null;
        }

        if (($closure = $this->DeclaredSyncOperationClosures[$entity->Class][$operation] ?? false) === false)
        {
            if ($method = $this->getSyncOperationMethod($operation, $entity))
            {
                $closure = fn(...$args) => $this->$method(...$args);
            }

            $this->DeclaredSyncOperationClosures[$entity->Class][$operation] = $closure ?: null;
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
    final public function getMagicSyncOperationClosure(string $method, ISyncProvider $provider): ?Closure
    {
        if (!$this->IsProvider)
        {
            return null;
        }

        if (($closure = $this->MagicSyncOperationClosures[$method = strtolower($method)] ?? false) === false)
        {
            if ($operation = $this->SyncOperationMagicMethods[$method] ?? null)
            {
                [$operation, $entity] = $operation;

                $closure =
                    function (SyncContext $ctx, ...$args) use ($entity, $operation)
                    {
                        /** @var ISyncProvider $this */
                        return $this->with($entity, $ctx)->run($operation, ...$args);
                    };
            }

            $this->MagicSyncOperationClosures[$method] = $closure ?: null;
        }

        return $closure ? $closure->bindTo($provider) : null;
    }

    private function getSyncOperationMethod(int $operation, SyncClosureBuilder $entity): ?string
    {
        [$noun, $plural] = [strtolower($entity->EntityNoun), strtolower($entity->EntityPlural)];

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
                $methods[] = "createlist_" . $noun;
                break;

            case SyncOperation::READ_LIST:
                $methods[] = "getlist_" . $noun;
                break;

            case SyncOperation::UPDATE_LIST:
                $methods[] = "updatelist_" . $noun;
                break;

            case SyncOperation::DELETE_LIST:
                $methods[] = "deletelist_" . $noun;
                break;
        }

        $methods = array_intersect_key($this->SyncOperationMethods, array_flip($methods ?? []));

        if (count($methods) > 1)
        {
            throw new RuntimeException("Too many implementations: " . implode(", ", $methods));
        }

        return reset($methods) ?: null;
    }

}
