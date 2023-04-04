<?php declare(strict_types=1);

namespace Lkrms\Sync\Support;

use Closure;
use Lkrms\Contract\HasBuilder;
use Lkrms\Contract\IContainer;
use Lkrms\Contract\IPipeline;
use Lkrms\Support\ArrayKeyConformity;
use Lkrms\Sync\Concept\DbSyncProvider;
use Lkrms\Sync\Concept\SyncDefinition;
use Lkrms\Sync\Contract\ISyncContext;
use Lkrms\Sync\Contract\ISyncEntity;
use Lkrms\Sync\Support\SyncOperation;
use UnexpectedValueException;

/**
 * Provides direct access to a DbSyncProvider's implementation of sync
 * operations for an entity
 *
 * @template TEntity of ISyncEntity
 * @template TProvider of DbSyncProvider
 * @extends SyncDefinition<TEntity,TProvider>
 */
class DbSyncDefinition extends SyncDefinition implements HasBuilder
{
    /**
     * @var string|null
     */
    protected $Table;

    /**
     * @param class-string<TEntity> $entity
     * @param TProvider $provider
     * @param int[] $operations
     * @phpstan-param array<SyncOperation::*> $operations
     * @phpstan-param ArrayKeyConformity::* $conformity
     * @phpstan-param SyncFilterPolicy::* $filterPolicy
     * @param array<int,Closure> $overrides
     * @phpstan-param array<SyncOperation::*,Closure> $overrides
     * @phpstan-param IPipeline<array,TEntity,array{0:int,1:ISyncContext,2?:int|string|ISyncEntity|ISyncEntity[]|null,...}>|null $dataToEntityPipeline
     * @phpstan-param IPipeline<TEntity,array,array{0:int,1:ISyncContext,2?:int|string|ISyncEntity|ISyncEntity[]|null,...}>|null $entityToDataPipeline
     */
    public function __construct(
        string $entity,
        DbSyncProvider $provider,
        array $operations = [],
        ?string $table = null,
        int $conformity = ArrayKeyConformity::PARTIAL,
        int $filterPolicy = SyncFilterPolicy::THROW_EXCEPTION,
        array $overrides = [],
        ?IPipeline $dataToEntityPipeline = null,
        ?IPipeline $entityToDataPipeline = null
    ) {
        parent::__construct(
            $entity,
            $provider,
            $operations,
            $conformity,
            $filterPolicy,
            $overrides,
            $dataToEntityPipeline,
            $entityToDataPipeline
        );

        $this->Table = $table;
    }

    protected function getClosure(int $operation): ?Closure
    {
        // Return null if no table name has been provided
        if (is_null($this->Table)) {
            return null;
        }

        switch ($operation) {
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

        return $closure;
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
