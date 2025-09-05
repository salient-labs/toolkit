<?php declare(strict_types=1);

namespace Salient\Sync\Db;

use Salient\Contract\Core\Pipeline\ArrayMapperInterface;
use Salient\Contract\Core\Pipeline\PipelineInterface;
use Salient\Contract\Core\Buildable;
use Salient\Contract\Sync\EntitySource;
use Salient\Contract\Sync\FilterPolicy;
use Salient\Contract\Sync\SyncContextInterface;
use Salient\Contract\Sync\SyncEntityInterface;
use Salient\Contract\Sync\SyncOperation as OP;
use Salient\Core\Concern\BuildableTrait;
use Salient\Sync\Support\SyncPipelineArgument;
use Salient\Sync\AbstractSyncDefinition;
use Closure;
use LogicException;

/**
 * Generates closures that use a DbSyncProvider to perform sync operations on an
 * entity
 *
 * @phpstan-type OverrideClosure (Closure(static, OP::*, SyncContextInterface, int|string|null, mixed...): TEntity)|(Closure(static, OP::*, SyncContextInterface, mixed...): iterable<array-key,TEntity>)|(Closure(static, OP::*, SyncContextInterface, TEntity, mixed...): TEntity)|(Closure(static, OP::*, SyncContextInterface, iterable<TEntity>, mixed...): iterable<array-key,TEntity>)
 *
 * @property-read string|null $Table
 *
 * @template TEntity of SyncEntityInterface
 * @template TProvider of DbSyncProvider
 *
 * @extends AbstractSyncDefinition<TEntity,TProvider>
 * @implements Buildable<DbSyncDefinitionBuilder<TEntity,TProvider>>
 */
final class DbSyncDefinition extends AbstractSyncDefinition implements Buildable
{
    /** @use BuildableTrait<DbSyncDefinitionBuilder<TEntity,TProvider>> */
    use BuildableTrait;

    /** @var string|null */
    protected $Table;

    /**
     * @internal
     *
     * @param class-string<TEntity> $entity
     * @param TProvider $provider
     * @param array<OP::*> $operations
     * @param DbSyncDefinition::* $conformity
     * @param FilterPolicy::*|null $filterPolicy
     * @param array<int-mask-of<OP::*>,Closure(DbSyncDefinition<TEntity,TProvider>, OP::*, SyncContextInterface, mixed...): (iterable<array-key,TEntity>|TEntity)> $overrides
     * @param array<array-key,array-key|array-key[]>|null $keyMap
     * @param int-mask-of<ArrayMapperInterface::REMOVE_NULL|ArrayMapperInterface::ADD_UNMAPPED|ArrayMapperInterface::ADD_MISSING|ArrayMapperInterface::REQUIRE_MAPPED> $keyMapFlags
     * @param PipelineInterface<mixed[],TEntity,SyncPipelineArgument>|null $pipelineFromBackend
     * @param PipelineInterface<TEntity,mixed[],SyncPipelineArgument>|null $pipelineToBackend
     * @param EntitySource::*|null $returnEntitiesFrom
     * @phpstan-param array<int-mask-of<OP::*>,OverrideClosure> $overrides
     */
    public function __construct(
        string $entity,
        DbSyncProvider $provider,
        array $operations = [],
        ?string $table = null,
        int $conformity = DbSyncDefinition::CONFORMITY_PARTIAL,
        ?int $filterPolicy = null,
        array $overrides = [],
        ?array $keyMap = null,
        int $keyMapFlags = ArrayMapperInterface::ADD_UNMAPPED,
        ?PipelineInterface $pipelineFromBackend = null,
        ?PipelineInterface $pipelineToBackend = null,
        bool $readFromList = false,
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
            $readFromList,
            $returnEntitiesFrom
        );

        $this->Table = $table;
    }

    /**
     * @inheritDoc
     */
    protected function getClosure(int $operation): ?Closure
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
