<?php declare(strict_types=1);

namespace Lkrms\Sync\Support;

use Lkrms\Concern\HasBuilder;
use Lkrms\Contract\IPipeline;
use Lkrms\Contract\ProvidesBuilder;
use Lkrms\Support\Catalog\ArrayKeyConformity;
use Lkrms\Sync\Catalog\SyncEntitySource;
use Lkrms\Sync\Catalog\SyncFilterPolicy;
use Lkrms\Sync\Catalog\SyncOperation;
use Lkrms\Sync\Concept\DbSyncProvider;
use Lkrms\Sync\Concept\SyncDefinition;
use Lkrms\Sync\Contract\ISyncContext;
use Lkrms\Sync\Contract\ISyncDefinition;
use Lkrms\Sync\Contract\ISyncEntity;
use Closure;
use LogicException;

/**
 * Provides direct access to a DbSyncProvider's implementation of sync
 * operations for an entity
 *
 * @template TEntity of ISyncEntity
 * @template TProvider of DbSyncProvider
 *
 * @property-read string|null $Table
 *
 * @extends SyncDefinition<TEntity,TProvider>
 * @implements ProvidesBuilder<DbSyncDefinitionBuilder<TEntity,TProvider>>
 */
final class DbSyncDefinition extends SyncDefinition implements ProvidesBuilder
{
    use HasBuilder;

    /**
     * @var string|null
     */
    protected $Table;

    /**
     * @param class-string<TEntity> $entity
     * @param TProvider $provider
     * @param array<SyncOperation::*> $operations
     * @param ArrayKeyConformity::* $conformity
     * @param SyncFilterPolicy::* $filterPolicy
     * @param array<SyncOperation::*,Closure(ISyncDefinition<TEntity,TProvider>, SyncOperation::*, ISyncContext, mixed...): mixed> $overrides
     * @param IPipeline<mixed[],TEntity,array{0:int,1:ISyncContext,2?:int|string|TEntity|TEntity[]|null,...}>|null $pipelineFromBackend
     * @param IPipeline<TEntity,mixed[],array{0:int,1:ISyncContext,2?:int|string|TEntity|TEntity[]|null,...}>|null $pipelineToBackend
     * @param SyncEntitySource::*|null $returnEntitiesFrom
     */
    public function __construct(
        string $entity,
        DbSyncProvider $provider,
        array $operations = [],
        ?string $table = null,
        int $conformity = ArrayKeyConformity::PARTIAL,
        int $filterPolicy = SyncFilterPolicy::THROW_EXCEPTION,
        array $overrides = [],
        ?IPipeline $pipelineFromBackend = null,
        ?IPipeline $pipelineToBackend = null,
        ?int $returnEntitiesFrom = null
    ) {
        parent::__construct(
            $entity,
            $provider,
            $operations,
            $conformity,
            $filterPolicy,
            $overrides,
            $pipelineFromBackend,
            $pipelineToBackend,
            $returnEntitiesFrom
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
                throw new LogicException("Invalid SyncOperation: $operation");
        }

        return $closure;
    }

    public static function getReadable(): array
    {
        return [
            ...parent::getReadable(),
            'Table',
        ];
    }

    /**
     * @inheritDoc
     */
    public static function getBuilder(): string
    {
        return DbSyncDefinitionBuilder::class;
    }
}
