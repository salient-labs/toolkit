<?php

declare(strict_types=1);

namespace Lkrms\Sync\Support;

use Closure;
use Lkrms\Facade\Convert;
use Lkrms\Support\ClosureBuilder;
use Lkrms\Sync\Contract\ISyncProvider;
use Lkrms\Sync\SyncEntity;
use Lkrms\Sync\SyncOperation;
use ReflectionClass;
use ReflectionMethod;
use RuntimeException;

class SyncClosureBuilder extends ClosureBuilder
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
     * @var string[]
     */
    private $SyncOperationMethods = [];

    /**
     * @var array<string,array<int,Closure>>
     */
    private $SyncOperationClosures = [];

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
        }
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
