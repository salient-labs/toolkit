<?php

declare(strict_types=1);

namespace Lkrms\Sync\Support;

use Closure;
use Lkrms\Contract\HasBuilder;
use Lkrms\Contract\IContainer;
use Lkrms\Contract\IPipelineImmutable;
use Lkrms\Support\ArrayKeyConformity;
use Lkrms\Sync\Concept\DbSyncProvider;
use Lkrms\Sync\Concept\SyncDefinition;
use Lkrms\Sync\Support\SyncOperation;
use UnexpectedValueException;

class DbSyncDefinition extends SyncDefinition implements HasBuilder
{
    /**
     * @var DbSyncProvider
     */
    protected $Provider;

    /**
     * @var int[]
     */
    protected $Operations;

    /**
     * @var string|null
     */
    protected $Table;

    /**
     * @var array<int,Closure>
     */
    protected $Overrides;

    /**
     * @var array<int,Closure>
     */
    private $Closures = [];

    /**
     * @param int[] $operations
     * @param array<int,Closure> $overrides
     */
    public function __construct(string $entity, DbSyncProvider $provider, array $operations = [], ?string $table = null, int $conformity = ArrayKeyConformity::PARTIAL, int $filterPolicy = SyncFilterPolicy::THROW_EXCEPTION, array $overrides = [], ?IPipelineImmutable $dataToEntityPipeline = null, ?IPipelineImmutable $entityToDataPipeline = null)
    {
        parent::__construct($entity, $provider, $conformity, $filterPolicy, $dataToEntityPipeline, $entityToDataPipeline);

        // Combine overridden operations with $operations and remove invalid
        // values
        $this->Operations = array_intersect(
            SyncOperation::getAll(),
            array_merge(array_values($operations), array_keys($overrides))
        );
        $this->Table     = $table;
        $this->Overrides = array_intersect_key($overrides, array_flip($this->Operations));
    }

    public function getSyncOperationClosure(int $operation): ?Closure
    {
        if (array_key_exists($operation, $this->Closures))
        {
            return $this->Closures[$operation];
        }

        // Overrides take precedence over everything else, including declared
        // methods
        if (array_key_exists($operation, $this->Overrides))
        {
            return $this->Closures[$operation] = $this->Overrides[$operation];
        }

        // If a method has been declared for this operation, use it, even if
        // it's not in $this->Operations
        if ($closure = $this->ProviderClosureBuilder->getDeclaredSyncOperationClosure($operation, $this->EntityClosureBuilder, $this->Provider))
        {
            return $this->Closures[$operation] = $closure;
        }

        // Return null if the operation doesn't appear in $this->Operations, or
        // if no table name has been provided
        if (!array_key_exists($operation, $this->Operations) || is_null($this->Table))
        {
            return $this->Closures[$operation] = null;
        }

        switch ($operation)
        {
            case SyncOperation::CREATE:
            case SyncOperation::READ:
            case SyncOperation::UPDATE:
            case SyncOperation::DELETE:
            case SyncOperation::CREATE_LIST:
            case SyncOperation::READ_LIST:
            case SyncOperation::UPDATE_LIST:
            case SyncOperation::DELETE_LIST:
                $closure = null;
                break;

            default:
                throw new UnexpectedValueException("Invalid SyncOperation: $operation");
        }

        return $this->Closures[$operation] = $closure;
    }

    /**
     * Use a fluent interface to create a new DbSyncDefinition object
     *
     */
    public static function build(?IContainer $container = null): DbSyncDefinitionBuilder
    {
        return new DbSyncDefinitionBuilder($container);
    }

    /**
     * @param DbSyncDefinitionBuilder|DbSyncDefinition|null $object
     */
    public static function resolve($object): DbSyncDefinition
    {
        return DbSyncDefinitionBuilder::resolve($object);
    }

}
