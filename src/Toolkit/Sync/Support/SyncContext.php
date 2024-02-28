<?php declare(strict_types=1);

namespace Salient\Sync\Support;

use Salient\Catalog\Sync\DeferralPolicy;
use Salient\Catalog\Sync\HydrationPolicy;
use Salient\Catalog\Sync\SyncOperation;
use Salient\Contract\Sync\SyncContextInterface;
use Salient\Contract\Sync\SyncEntityInterface;
use Salient\Contract\Sync\SyncProviderInterface;
use Salient\Core\Utility\Arr;
use Salient\Core\Utility\Get;
use Salient\Core\Utility\Pcre;
use Salient\Core\Utility\Str;
use Salient\Core\ProviderContext;
use Salient\Sync\Exception\SyncEntityRecursionException;
use Salient\Sync\Exception\SyncInvalidFilterException;
use LogicException;

/**
 * The context within which sync entities are instantiated by a provider
 *
 * @extends ProviderContext<SyncProviderInterface,SyncEntityInterface>
 */
final class SyncContext extends ProviderContext implements SyncContextInterface
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
     * @var (callable(SyncContextInterface, ?bool &$returnEmpty, array{}|null &$empty): void)|null
     */
    protected $FilterPolicyCallback;

    protected ?bool $Offline = null;

    /**
     * @var DeferralPolicy::*
     */
    protected int $DeferralPolicy = DeferralPolicy::RESOLVE_EARLY;

    /**
     * Entity => depth => policy
     *
     * @var array<class-string<SyncEntityInterface>,array<int<0,max>,int&HydrationPolicy::*>>
     */
    protected array $EntityHydrationPolicy = [];

    /**
     * @var array<int<0,max>,HydrationPolicy::*>
     */
    protected array $FallbackHydrationPolicy = [0 => HydrationPolicy::DEFER];

    protected ?SyncEntityInterface $LastRecursedInto = null;

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
    public function online()
    {
        return $this->withPropertyValue('Offline', false);
    }

    /**
     * @inheritDoc
     */
    public function offline()
    {
        return $this->withPropertyValue('Offline', true);
    }

    /**
     * @inheritDoc
     */
    public function offlineFirst()
    {
        return $this->withPropertyValue('Offline', null);
    }

    /**
     * @inheritDoc
     */
    public function applyFilterPolicy(?bool &$returnEmpty, ?array &$empty): void
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

                $key = Str::toSnakeCase($key);
                if ($key === '') {
                    throw new SyncInvalidFilterException(...$args);
                }

                $filters[$key] = $this->reduceFilterValue($value);

                if (substr($key, -3) !== '_id') {
                    continue;
                }

                $name = Str::toSnakeCase(substr($key, 0, -3));
                if ($name !== '') {
                    $filterKeys[$name] = $key;
                }
            }
            return $this->applyFilters($filters ?? [], $filterKeys ?? []);
        }

        if (Arr::ofArrayKey($args)) {
            return $this->applyFilters(['id' => $args]);
        }

        if (Arr::of($args, SyncEntityInterface::class)) {
            return $this->applyFilters(
                array_merge_recursive(
                    ...array_map(
                        fn(SyncEntityInterface $entity): array => [
                            Str::toSnakeCase(
                                Get::basename(
                                    $entity->getService()
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
     * @param int&HydrationPolicy::* $policy
     */
    public function withHydrationPolicy(
        int $policy,
        ?string $entity = null,
        $depth = null
    ) {
        // @phpstan-ignore-next-line
        if ($depth !== null && array_filter((array) $depth, fn($depth) => $depth < 1)) {
            throw new LogicException('$depth must be greater than 0');
        }

        $clone = $this->clone();
        $clone->applyHydrationPolicy($policy, $entity, $depth);

        if ($this->EntityHydrationPolicy === $clone->EntityHydrationPolicy &&
                $this->FallbackHydrationPolicy === $clone->FallbackHydrationPolicy) {
            return $this;
        }

        return $clone;
    }

    /**
     * @inheritDoc
     */
    public function pushWithRecursionCheck(SyncEntityInterface $entity)
    {
        return $this
            ->push($entity)
            ->withPropertyValue(
                'LastRecursedInto',
                in_array($entity, $this->Stack, true) ? $entity : null,
            );
    }

    /**
     * @inheritDoc
     */
    public function maybeThrowRecursionException(): void
    {
        if (
            $this->LastRecursedInto &&
            $this->LastRecursedInto === end($this->Stack)
        ) {
            throw new SyncEntityRecursionException(sprintf(
                'Circular reference detected: %s',
                $this->LastRecursedInto->uri(),
            ));
        }
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
    public function getOffline(): ?bool
    {
        return $this->Offline;
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
    public function getHydrationPolicy(?string $entity): int
    {
        $depth = count($this->Stack) + 1;

        if ($entity !== null && $this->EntityHydrationPolicy) {
            $applied = false;
            $flags = 0;
            foreach ($this->EntityHydrationPolicy as $entityType => $values) {
                if (!is_a($entityType, $entity, true)) {
                    continue;
                }
                $value = $values[$depth] ?? $values[0] ?? null;
                if ($value === null) {
                    continue;
                }
                $flags |= $value;
                $applied = true;
            }
            if ($applied) {
                return $flags;
            }
        }

        return $this->FallbackHydrationPolicy[$depth]
            ?? $this->FallbackHydrationPolicy[0]
            ?? HydrationPolicy::DEFER;
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
     *
     * @param SyncEntityInterface|SyncEntityInterface[]|T $value
     * @return int|string|array<int|string>|T
     */
    private function reduceFilterValue($value)
    {
        if ($value instanceof SyncEntityInterface) {
            return $value->id();
        }
        if (Arr::of($value, SyncEntityInterface::class)) {
            $ids = [];
            /** @var SyncEntityInterface $entity */
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
            $key = Str::toSnakeCase($key);
            if (!array_key_exists($key, $this->Filters)) {
                if (substr($key, -3) !== '_id') {
                    return $orValue ? $this->getValue($key) : null;
                }
                $name = Str::toSnakeCase(substr($key, 0, -3));
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

    /**
     * @param int&HydrationPolicy::* $policy
     * @param class-string<SyncEntityInterface>|null $entity
     * @param array<int<1,max>>|int<1,max>|null $depth
     */
    private function applyHydrationPolicy(
        int $policy,
        ?string $entity,
        $depth
    ): void {
        $currentDepth = count($this->Stack);

        if ($entity === null && $depth === null) {
            $this->EntityHydrationPolicy = [];
            $this->FallbackHydrationPolicy = [0 => $policy];
            return;
        }

        if ($entity === null) {
            $this->FallbackHydrationPolicy = $this->doApplyHydrationPolicy(
                $policy,
                $depth,
                $currentDepth,
                $this->FallbackHydrationPolicy,
            );
        } else {
            $this->EntityHydrationPolicy +=
                [$entity => $this->FallbackHydrationPolicy];
        }

        foreach ($this->EntityHydrationPolicy as $entityType => &$value) {
            if ($entity === null || is_a($entityType, $entity, true)) {
                $value = $this->doApplyHydrationPolicy(
                    $policy,
                    $depth,
                    $currentDepth,
                    $value,
                );
            }
        }
    }

    /**
     * @param HydrationPolicy::* $policy
     * @param array<int<1,max>>|int<1,max>|null $depth
     * @param array<int<0,max>,HydrationPolicy::*> $currentPolicy
     * @return array<int<0,max>,HydrationPolicy::*>
     */
    private function doApplyHydrationPolicy(
        int $policy,
        $depth,
        int $currentDepth,
        array $currentPolicy
    ): array {
        if ($depth === null) {
            return [0 => $policy];
        }

        foreach ((array) $depth as $depth) {
            $currentPolicy[$currentDepth + $depth] = $policy;
        }
        return $currentPolicy;
    }
}
