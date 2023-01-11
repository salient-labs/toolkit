<?php declare(strict_types=1);

namespace Lkrms\Sync\Support;

use Closure;
use Lkrms\Facade\Convert;
use Lkrms\Support\Dictionary\Regex;
use Lkrms\Support\Introspector;
use Lkrms\Sync\Contract\ISyncProvider;
use Lkrms\Sync\Support\SyncOperation;
use RuntimeException;

/**
 * @property-read string|null $EntityNoun
 * @property-read string|null $EntityPlural Not set if the plural class name is the same the singular one
 *
 * @template TClass of object
 * @template TIntrospectionClass of SyncIntrospectionClass
 * @extends Introspector<TClass,SyncIntrospectionClass>
 * @todo Remove second @template tag after resolution of https://github.com/bmewburn/vscode-intelephense/issues/2434
 */
final class SyncIntrospector extends Introspector
{
    final public static function entityToProvider(string $entity): string
    {
        return sprintf('%s\Provider\%sProvider', Convert::classToNamespace($entity), Convert::classToBasename($entity));
    }

    final public static function providerToEntity(string $provider): ?string
    {
        if (preg_match('/^(?P<namespace>' . Regex::PHP_TYPE . '\\\\)?Provider\\\\(?P<class>' . Regex::PHP_IDENTIFIER . ')?Provider$/U',
                       $provider,
                       $matches)) {
            return $matches['namespace'] . $matches['class'];
        }

        return null;
    }

    protected function getIntrospectionClass(string $class): SyncIntrospectionClass
    {
        return new SyncIntrospectionClass($class);
    }

    /**
     * Get a list of ISyncProvider interfaces implemented by the provider
     *
     * @return string[]|null
     */
    final public function getSyncProviderInterfaces(): ?array
    {
        if (!$this->_Class->IsProvider) {
            return null;
        }

        return $this->_Class->SyncProviderInterfaces;
    }

    /**
     * Get a list of SyncEntity subclasses serviced by the provider
     *
     * @return string[]|null
     */
    final public function getSyncProviderEntities(): ?array
    {
        if (!$this->_Class->IsProvider) {
            return null;
        }

        return $this->_Class->SyncProviderEntities;
    }

    /**
     * Get an array that maps unambiguous lowercase entity basenames to
     * SyncEntity subclasses serviced by the provider
     *
     * @return array<string,string>|null
     */
    final public function getSyncProviderEntityBasenames(): ?array
    {
        if (!$this->_Class->IsProvider) {
            return null;
        }

        return $this->_Class->SyncProviderEntityBasenames;
    }

    /**
     * Get the SyncProvider method that implements a SyncOperation for an entity
     *
     * Returns `null` if:
     * - the {@see SyncIntrospector} was not created for an
     *   {@see ISyncProvider},
     * - `$entity` was not created for a {@see \Lkrms\Sync\Concept\SyncEntity}
     *   subclass, or
     * - the {@see ISyncProvider} class doesn't implement the given
     *   {@see SyncOperation} via a method
     *
     * @param int $operation A {@see SyncOperation} value.
     * @psalm-param SyncOperation::* $operation
     * @param string|SyncIntrospector $entity
     * @return Closure(SyncContext, mixed...)|null
     * ```php
     * fn(SyncContext $ctx, ...$args)
     * ```
     */
    final public function getDeclaredSyncOperationClosure(int $operation, $entity, ISyncProvider $provider): ?Closure
    {
        if (!($entity instanceof SyncIntrospector)) {
            $entity = static::get($entity);
        }
        $_entity = $entity->_Class;

        if (!$this->_Class->IsProvider || !$_entity->IsEntity) {
            return null;
        }

        if (($closure = $this->_Class->DeclaredSyncOperationClosures[$_entity->Class][$operation] ?? false) === false) {
            if ($method = $this->getSyncOperationMethod($operation, $entity)) {
                $closure = fn(...$args) => $this->$method(...$args);
            }

            $this->_Class->DeclaredSyncOperationClosures[$_entity->Class][$operation] = $closure ?: null;
        }

        return $closure ? $closure->bindTo($provider) : null;
    }

    /**
     * Get a closure to perform sync operations on behalf of a provider's
     * "magic" method
     *
     * Returns `null` if:
     * - the {@see SyncIntrospector} was not created for an
     *   {@see ISyncProvider},
     * - the {@see ISyncProvider} class has already has `$method`, or
     * - `$method` doesn't resolve to an unambiguous sync operation on a
     *   {@see \Lkrms\Sync\Concept\SyncEntity} subclass serviced by the
     *   {@see ISyncProvider} class
     *
     * @return Closure(SyncContext, mixed...)|null
     * ```php
     * fn(SyncContext $ctx, ...$args)
     * ```
     */
    final public function getMagicSyncOperationClosure(string $method, ISyncProvider $provider): ?Closure
    {
        if (!$this->_Class->IsProvider) {
            return null;
        }

        if (($closure = $this->_Class->MagicSyncOperationClosures[$method = strtolower($method)] ?? false) === false) {
            if ($operation = $this->_Class->SyncOperationMagicMethods[$method] ?? null) {
                [$operation, $entity] = $operation;
                $closure              =
                    function (SyncContext $ctx, ...$args) use ($entity, $operation) {
                        /** @var ISyncProvider $this */
                        return $this->with($entity, $ctx)->run($operation, ...$args);
                    };
            }
            $this->_Class->MagicSyncOperationClosures[$method] = $closure ?: null;
        }

        return $closure ? $closure->bindTo($provider) : null;
    }

    private function getSyncOperationMethod(int $operation, SyncIntrospector $entity): ?string
    {
        $_entity         = $entity->_Class;
        [$noun, $plural] = [strtolower($_entity->EntityNoun), strtolower($_entity->EntityPlural)];

        if ($plural) {
            switch ($operation) {
                case SyncOperation::CREATE_LIST:
                    $methods[] = 'create' . $plural;
                    break;

                case SyncOperation::READ_LIST:
                    $methods[] = 'get' . $plural;
                    break;

                case SyncOperation::UPDATE_LIST:
                    $methods[] = 'update' . $plural;
                    break;

                case SyncOperation::DELETE_LIST:
                    $methods[] = 'delete' . $plural;
                    break;
            }
        }
        switch ($operation) {
            case SyncOperation::CREATE:
                $methods[] = 'create' . $noun;
                $methods[] = 'create_' . $noun;
                break;

            case SyncOperation::READ:
                $methods[] = 'get' . $noun;
                $methods[] = 'get_' . $noun;
                break;

            case SyncOperation::UPDATE:
                $methods[] = 'update' . $noun;
                $methods[] = 'update_' . $noun;
                break;

            case SyncOperation::DELETE:
                $methods[] = 'delete' . $noun;
                $methods[] = 'delete_' . $noun;
                break;

            case SyncOperation::CREATE_LIST:
                $methods[] = 'createlist_' . $noun;
                break;

            case SyncOperation::READ_LIST:
                $methods[] = 'getlist_' . $noun;
                break;

            case SyncOperation::UPDATE_LIST:
                $methods[] = 'updatelist_' . $noun;
                break;

            case SyncOperation::DELETE_LIST:
                $methods[] = 'deletelist_' . $noun;
                break;
        }
        $methods = array_intersect_key($this->_Class->SyncOperationMethods, array_flip($methods ?? []));
        if (count($methods) > 1) {
            throw new RuntimeException('Too many implementations: ' . implode(', ', $methods));
        }

        return reset($methods) ?: null;
    }
}
