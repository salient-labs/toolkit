<?php declare(strict_types=1);

namespace Salient\Sync\Support;

use Salient\Core\Catalog\ArrayMapperFlag;
use Salient\Core\Catalog\ListConformity;
use Salient\Core\Concern\HasBuilder;
use Salient\Core\Contract\Buildable;
use Salient\Core\Contract\PipelineInterface;
use Salient\Sync\Catalog\FilterPolicy;
use Salient\Sync\Catalog\SyncEntitySource;
use Salient\Sync\Catalog\SyncOperation as OP;
use Salient\Sync\Concept\DbSyncProvider;
use Salient\Sync\Concept\SyncDefinition;
use Salient\Sync\Contract\ISyncContext;
use Salient\Sync\Contract\ISyncEntity;
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
 * @implements Buildable<DbSyncDefinitionBuilder<TEntity,TProvider>>
 */
final class DbSyncDefinition extends SyncDefinition implements Buildable
{
    /** @use HasBuilder<DbSyncDefinitionBuilder<TEntity,TProvider>> */
    use HasBuilder;

    /**
     * @var string|null
     */
    protected $Table;

    /**
     * @param class-string<TEntity> $entity
     * @param TProvider $provider
     * @param array<OP::*> $operations
     * @param ListConformity::* $conformity
     * @param FilterPolicy::*|null $filterPolicy
     * @param array<int-mask-of<OP::*>,Closure(DbSyncDefinition<TEntity,TProvider>, OP::*, ISyncContext, mixed...): (iterable<TEntity>|TEntity)> $overrides
     * @param array<array-key,array-key|array-key[]>|null $keyMap
     * @param int-mask-of<ArrayMapperFlag::*> $keyMapFlags
     * @param PipelineInterface<mixed[],TEntity,array{0:OP::*,1:ISyncContext,2?:int|string|TEntity|TEntity[]|null,...}>|null $pipelineFromBackend
     * @param PipelineInterface<TEntity,mixed[],array{0:OP::*,1:ISyncContext,2?:int|string|TEntity|TEntity[]|null,...}>|null $pipelineToBackend
     * @param SyncEntitySource::*|null $returnEntitiesFrom
     */
    public function __construct(
        string $entity,
        DbSyncProvider $provider,
        array $operations = [],
        ?string $table = null,
        $conformity = ListConformity::PARTIAL,
        ?int $filterPolicy = null,
        array $overrides = [],
        ?array $keyMap = null,
        int $keyMapFlags = ArrayMapperFlag::ADD_UNMAPPED,
        ?PipelineInterface $pipelineFromBackend = null,
        ?PipelineInterface $pipelineToBackend = null,
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

    public static function getReadableProperties(): array
    {
        return [
            ...parent::getReadableProperties(),
            'Table',
        ];
    }
}
