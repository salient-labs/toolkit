<?php declare(strict_types=1);

namespace Lkrms\Sync\Support;

use Lkrms\Support\ProviderContext;
use Lkrms\Sync\Catalog\DeferralPolicy;
use Lkrms\Sync\Catalog\SyncEntityHydrationFlag as HydrationFlag;
use Lkrms\Sync\Catalog\SyncOperation;
use Lkrms\Sync\Contract\ISyncContext;
use Lkrms\Sync\Contract\ISyncEntity;
use Lkrms\Sync\Exception\SyncInvalidFilterException;
use Lkrms\Utility\Convert;
use Lkrms\Utility\Pcre;
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
     * @var array<string,string>
     */
    protected array $FilterKeys = [];

    /**
     * @var (callable(ISyncContext, ?bool &$returnEmpty, mixed &$empty): void)|null
     */
    protected $FilterPolicyCallback;

    /**
     * @var DeferralPolicy::*
     */
    protected $DeferralPolicy = DeferralPolicy::RESOLVE_EARLY;

    /**
     * @var array<class-string<ISyncEntity>,int-mask-of<HydrationFlag::*>>
     */
    protected $EntityHydrationFlags = [];

    /**
     * @var int-mask-of<HydrationFlag::*>
     */
    protected $FallbackHydrationFlags = HydrationFlag::DEFER;

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

        if (!$args) {
            return $this->applyFilters();
        }

        if (is_array($args[0]) && count($args) === 1) {
            foreach ($args[0] as $key => $value) {
                if (Pcre::match('/[^[:alnum:]_-]/', $key)) {
                    $filters[$key] = $this->reduceFilterValue($value);
                    continue;
                }

                $key = Convert::toSnakeCase($key);
                if ($key === '') {
                    throw new SyncInvalidFilterException(...$args);
                }

                $filters[$key] = $this->reduceFilterValue($value);

                if (substr($key, -3) !== '_id') {
                    continue;
                }

                $name = Convert::toSnakeCase(substr($key, 0, -3));
                if ($name !== '') {
                    $filterKeys[$name] = $key;
                }
            }
            return $this->applyFilters($filters ?? [], $filterKeys ?? []);
        }

        if (Test::isArrayOfArrayKey($args)) {
            return $this->applyFilters(['id' => $args]);
        }

        if (Test::isArrayOf($args, ISyncEntity::class)) {
            return $this->applyFilters(
                array_merge_recursive(
                    ...array_map(
                        fn(ISyncEntity $entity): array => [
                            Convert::toSnakeCase(
                                Convert::classToBasename(
                                    $entity->service()
                                )
                            ) => [
                                $entity->id(),
                            ],
                        ],
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
    public function withDeferralPolicy($policy)
    {
        return $this->withPropertyValue('DeferralPolicy', $policy);
    }

    /**
     * @inheritDoc
     */
    public function withHydrationFlags(int $flags, bool $replace = true, ?string $entity = null)
    {
        $clone = $this->mutate();

        if ($entity === null) {
            if ($replace) {
                $clone->EntityHydrationFlags = [];
                $clone->FallbackHydrationFlags = $flags;
                return $clone;
            }

            foreach ($clone->EntityHydrationFlags as &$value) {
                $value |= $flags;
            }
            $clone->FallbackHydrationFlags |= $flags;
            return $clone;
        }

        if ($replace) {
            foreach ($clone->EntityHydrationFlags as $entityType => &$value) {
                if (is_a($entityType, $entity, true)) {
                    $value = $flags;
                }
            }
            $clone->EntityHydrationFlags[$entity] = $flags;
            return $clone;
        }

        foreach ($clone->EntityHydrationFlags as $entityType => &$value) {
            if (is_a($entityType, $entity, true)) {
                $value |= $flags;
            }
        }
        $clone->EntityHydrationFlags[$entity] =
            ($clone->EntityHydrationFlags[$entity]
                ?? $clone->FallbackHydrationFlags) | $flags;

        return $clone;
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
    public function getFilter(string $key, bool $orValue = true)
    {
        return $this->doGetFilter($key, $orValue);
    }

    /**
     * @inheritDoc
     */
    public function claimFilter(string $key, bool $orValue = true)
    {
        return $this->doGetFilter($key, $orValue, true);
    }

    /**
     * @inheritDoc
     */
    public function getDeferralPolicy()
    {
        return $this->DeferralPolicy;
    }

    /**
     * @inheritDoc
     */
    public function getHydrationFlags(string $entity)
    {
        if ($this->EntityHydrationFlags) {
            $applied = false;
            $flags = 0;
            foreach ($this->EntityHydrationFlags as $entityType => $value) {
                if (is_a($entityType, $entity, true)) {
                    $flags |= $value;
                    $applied = true;
                }
            }
            if ($applied) {
                return $flags;
            }
        }

        return $this->FallbackHydrationFlags;
    }

    /**
     * @param array<string,mixed> $filters
     * @param array<string,string> $filterKeys
     * @return $this
     */
    private function applyFilters(array $filters = [], array $filterKeys = [])
    {
        return $this
            ->withPropertyValue('Filters', $filters)
            ->withPropertyValue('FilterKeys', $filterKeys);
    }

    /**
     * @template T
     * @param ISyncEntity|ISyncEntity[]|T $value
     * @return int|string|array<int|string>|T
     */
    private function reduceFilterValue($value)
    {
        if ($value instanceof ISyncEntity) {
            return $value->id();
        }
        if (Test::isArrayOf($value, ISyncEntity::class)) {
            $ids = [];
            /** @var ISyncEntity $entity */
            foreach ($value as $entity) {
                $ids[] = $entity->id();
            }
            return $ids;
        }
        return $value;
    }

    /**
     * @return mixed
     */
    private function doGetFilter(string $key, bool $orValue, bool $claim = false)
    {
        if (!array_key_exists($key, $this->Filters)) {
            $key = Convert::toSnakeCase($key);
            if (!array_key_exists($key, $this->Filters)) {
                if (substr($key, -3) !== '_id') {
                    return $orValue ? $this->getValue($key) : null;
                }
                $name = Convert::toSnakeCase(substr($key, 0, -3));
                if (array_key_exists($name, $this->FilterKeys)) {
                    $key = $this->FilterKeys[$name];
                    if ($claim) {
                        unset($this->FilterKeys[$name]);
                    }
                } elseif (array_key_exists($name, $this->Filters)) {
                    $key = $name;
                } else {
                    return $orValue ? $this->getValue($key) : null;
                }
            }
        }

        $value = $this->Filters[$key];

        if ($claim) {
            unset($this->Filters[$key]);
        }

        return $value;
    }
}
