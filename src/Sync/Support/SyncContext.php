<?php declare(strict_types=1);

namespace Lkrms\Sync\Support;

use Lkrms\Support\ProviderContext;
use Lkrms\Sync\Catalog\DeferredSyncEntityPolicy;
use Lkrms\Sync\Catalog\SyncOperation;
use Lkrms\Sync\Contract\ISyncContext;
use Lkrms\Sync\Contract\ISyncEntity;
use Lkrms\Sync\Exception\SyncInvalidFilterException;
use Lkrms\Utility\Convert;
use Lkrms\Utility\Test;

/**
 * The context within which a sync entity is instantiated by a provider
 */
final class SyncContext extends ProviderContext implements ISyncContext
{
    /**
     * @var array<string,mixed>
     */
    protected array $Filters = [];

    /**
     * @var (callable(ISyncContext, ?bool &$returnEmpty, mixed &$empty): void)|null
     */
    protected $FilterPolicyCallback;

    /**
     * @var DeferredSyncEntityPolicy::*
     */
    protected $DeferredSyncEntityPolicy = DeferredSyncEntityPolicy::RESOLVE_EARLY;

    /**
     * @inheritDoc
     */
    public function withFilterPolicyCallback(?callable $callback)
    {
        return $this->withPropertyValue('FilterPolicyCallback', $callback);
    }

    /**
     * @inheritDoc
     */
    public function maybeApplyFilterPolicy(?bool &$returnEmpty, &$empty): void
    {
        $returnEmpty = false;

        if ($this->FilterPolicyCallback) {
            ($this->FilterPolicyCallback)($this, $returnEmpty, $empty);
        }
    }

    /**
     * @inheritDoc
     */
    public function withArgs($operation, ...$args)
    {
        // READ_LIST is the only operation with no mandatory argument after
        // `SyncContext $ctx`
        if ($operation !== SyncOperation::READ_LIST) {
            array_shift($args);
        }

        if (empty($args)) {
            return $this->withPropertyValue('Filters', []);
        }

        if (is_array($args[0]) && count($args) === 1) {
            return $this->withPropertyValue('Filters', array_combine(
                array_map(
                    fn($key) =>
                        preg_match('/[^[:alnum:]_-]/', $key) ? $key : Convert::toSnakeCase($key),
                    array_keys($args[0])
                ),
                array_map(
                    fn($value) =>
                        $value instanceof ISyncEntity ? $value->id() : $value,
                    $args[0]
                )
            ));
        }

        if (Test::isArrayOfArrayKey($args)) {
            return $this->withPropertyValue('Filters', ['id' => $args]);
        }

        if (Test::isArrayOf($args, ISyncEntity::class)) {
            return $this->withPropertyValue(
                'Filter',
                array_merge_recursive(
                    ...array_map(
                        fn(ISyncEntity $entity): array =>
                            [Convert::toSnakeCase(Convert::classToBasename($entity->service())) =>
                                [$entity->id()]],
                        $args
                    )
                )
            );
        }

        throw new SyncInvalidFilterException(...$args);
    }

    /**
     * @inheritDoc
     */
    public function withDeferredSyncEntityPolicy($policy)
    {
        return $this->withPropertyValue('DeferredSyncEntityPolicy', $policy);
    }

    /**
     * @inheritDoc
     */
    public function getFilters(): array
    {
        return $this->Filters;
    }

    /**
     * @inheritDoc
     */
    public function claimFilter(string $key)
    {
        if (array_key_exists($key, $this->Filters)) {
            $value = $this->Filters[$key];
            unset($this->Filters[$key]);

            return $value;
        }

        return null;
    }

    /**
     * @inheritDoc
     */
    public function getDeferredSyncEntityPolicy()
    {
        return $this->DeferredSyncEntityPolicy;
    }
}
