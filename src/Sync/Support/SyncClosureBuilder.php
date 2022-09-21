<?php

declare(strict_types=1);

namespace Lkrms\Sync\Support;

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

    protected function load(ReflectionClass $class): void
    {
        parent::load($class);

        $this->Class      = $class->name;
        $this->IsEntity   = $class->isSubclassOf(SyncEntity::class);
        $this->IsProvider = $class->implementsInterface(ISyncProvider::class);

        if ($this->IsEntity)
        {
            $this->EntityNoun = Convert::classToBasename($this->Class);
            if (strcasecmp($this->EntityNoun,
                $plural = $class->getMethod("getPluralClassName")->invoke(null)))
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
     *   SyncOperation
     */
    final public function getSyncOperationMethod(int $operation, SyncClosureBuilder $entityClosureBuilder): ?string
    {
        if (!$this->IsProvider || !$entityClosureBuilder->IsEntity)
        {
            return null;
        }

        [$noun, $plural] = [$entityClosureBuilder->EntityNoun, $entityClosureBuilder->EntityPlural];

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
