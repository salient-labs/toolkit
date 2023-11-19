<?php declare(strict_types=1);

namespace Lkrms\Sync\Support;

use Lkrms\Concern\HasBuilder;
use Lkrms\Contract\IPipeline;
use Lkrms\Contract\ProvidesBuilder;
use Lkrms\Support\Catalog\ArrayKeyConformity;
use Lkrms\Support\Catalog\ArrayMapperFlag;
use Lkrms\Sync\Catalog\FilterPolicy;
use Lkrms\Sync\Catalog\SyncEntitySource;
use Lkrms\Sync\Catalog\SyncOperation as OP;
use Lkrms\Sync\Concept\DbSyncProvider;
use Lkrms\Sync\Concept\SyncDefinition;
use Lkrms\Sync\Contract\ISyncContext;
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
     * @param array<OP::*> $operations
     * @param ArrayKeyConformity::* $conformity
     * @param FilterPolicy::*|null $filterPolicy
     * @param array<int-mask-of<OP::*>,Closure(DbSyncDefinition<TEntity,TProvider>, OP::*, ISyncContext, mixed...): (iterable<TEntity>|TEntity)> $overrides
     * @param array<array-key,array-key|array-key[]>|null $keyMap
     * @param int-mask-of<ArrayMapperFlag::*> $keyMapFlags
     * @param IPipeline<mixed[],TEntity,array{0:OP::*,1:ISyncContext,2?:int|string|TEntity|TEntity[]|null,...}>|null $pipelineFromBackend
     * @param IPipeline<TEntity,mixed[],array{0:OP::*,1:ISyncContext,2?:int|string|TEntity|TEntity[]|null,...}>|null $pipelineToBackend
     * @param SyncEntitySource::*|null $returnEntitiesFrom
     */
    public function __construct(
        string $entity,
        DbSyncProvider $provider,
        array $operations = [],
        ?string $table = null,
        $conformity = ArrayKeyConformity::PARTIAL,
        ?int $filterPolicy = null,
        array $overrides = [],
        ?array $keyMap = null,
        int $keyMapFlags = ArrayMapperFlag::ADD_UNMAPPED,
        ?IPipeline $pipelineFromBackend = null,
        ?IPipeline $pipelineToBackend = null,
        bool $readFromReadList = false,
        ?int $returnEntitiesFrom = null
    ) {
        parent::__construct(
            $entity,
            $provider,
            $operations,
            $conformity,
            $filterPolicy,
            $overrides,
            $keyMap,
            $keyMapFlags,
            $pipelineFromBackend,
            $pipelineToBackend,
            $readFromReadList,
            $returnEntitiesFrom
        );

        $this->Table = $table;
    }

    protected function getClosure($operation): ?Closure
    {
        // Return null if no table name has been provided
        if ($this->Table === null) {
            return null;
        }

        switch ($operation) {
            case OP::CREATE:
            case OP::READ:
            case OP::UPDATE:
            case OP::DELETE:
            case OP::CREATE_LIST:
            case OP::READ_LIST:
            case OP::UPDATE_LIST:
            case OP::DELETE_LIST:
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
