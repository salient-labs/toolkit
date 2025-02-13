<?php declare(strict_types=1);

namespace Salient\Sync\Support;

use Salient\Contract\Sync\DeferralPolicy;
use Salient\Contract\Sync\HydrationPolicy;
use Salient\Contract\Sync\SyncContextInterface;
use Salient\Contract\Sync\SyncEntityInterface;
use Salient\Contract\Sync\SyncOperation;
use Salient\Contract\Sync\SyncProviderInterface;
use Salient\Core\Concern\ImmutableTrait;
use Salient\Core\Provider\ProviderContext;
use Salient\Sync\Exception\InvalidFilterException;
use Salient\Sync\Exception\InvalidFilterSignatureException;
use Salient\Utility\Arr;
use Salient\Utility\Get;
use Salient\Utility\Regex;
use Salient\Utility\Str;
use Salient\Utility\Test;
use DateTimeInterface;
use LogicException;

/**
 * The context within which sync entities are instantiated by a provider
 *
 * @extends ProviderContext<SyncProviderInterface,SyncEntityInterface>
 */
final class SyncContext extends ProviderContext implements SyncContextInterface
{
    use ImmutableTrait;

    /** @var SyncOperation::* */
    protected ?int $Operation = null;
    /** @var array<string,(int|string|float|bool|null)[]|int|string|float|bool|null> */
    protected array $Filters = [];
    /** @var array<string,string> */
    protected array $FilterKeys = [];
    protected ?bool $Offline = null;
    /** @var DeferralPolicy::* */
    protected int $DeferralPolicy = DeferralPolicy::RESOLVE_EARLY;

    /**
     * Entity => depth => policy
     *
     * @var array<class-string<SyncEntityInterface>,array<int<0,max>,int&HydrationPolicy::*>>
     */
    protected array $EntityHydrationPolicy = [];

    /** @var array<int<0,max>,HydrationPolicy::*> */
    protected array $FallbackHydrationPolicy = [0 => HydrationPolicy::DEFER];
    protected bool $RecursionDetected = false;
    /** @var array<class-string,string> */
    private static array $ServiceKeyMap;

    /**
     * @inheritDoc
     */
    public function pushEntity($entity, bool $detectRecursion = false)
    {
        return parent::pushEntity($entity)->with(
            'RecursionDetected',
            $detectRecursion && in_array($entity, $this->Entities, true)
        );
    }

    /**
     * @inheritDoc
     */
    public function recursionDetected(): bool
    {
        return $this->RecursionDetected;
    }

    /**
     * @inheritDoc
     */
    public function hasOperation(): bool
    {
        return $this->Operation !== null;
    }

    /**
     * @inheritDoc
     */
    public function getOperation(): ?int
    {
        return $this->Operation;
    }

    /**
     * @inheritDoc
     */
    public function hasFilter(?string $key = null): bool
    {
        return $key === null
            ? (bool) $this->Filters
            : $this->getFilterKey($key) !== null;
    }

    /**
     * @inheritDoc
     */
    public function getFilter(string $key, bool $orValue = true)
    {
        $_key = $this->getFilterKey($key);
        if ($_key === null) {
            return $orValue ? $this->getValue($key) : null;
        }
        return $this->Filters[$_key];
    }

    /**
     * @inheritDoc
     */
    public function claimFilter(string $key, bool $orValue = true)
    {
        $_key = $this->getFilterKey($key, $altKey);
        if ($_key === null) {
            return $orValue ? $this->getValue($key) : null;
        }
        $value = $this->Filters[$_key];
        unset($this->Filters[$_key]);
        if ($altKey !== null) {
            unset($this->FilterKeys[$altKey]);
        }
        return $value;
    }

    private function getFilterKey(string $key, ?string &$altKey = null): ?string
    {
        if (
            array_key_exists($key, $this->Filters)
            || array_key_exists($key = Str::snake($key), $this->Filters)
        ) {
            // @phpstan-ignore parameterByRef.type
            $altKey = Arr::search($this->FilterKeys, $key);
            return $key;
        }
        $altKey = $key;
        return $this->FilterKeys[$key] ?? null;
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
    public function withOperation(int $operation, string $entityType, ...$args)
    {
        return $this
            ->with('Operation', $operation)
            ->withEntityType($entityType)
            ->withArgs($operation, ...$args);
    }

    /**
     * @param SyncOperation::* $operation
     * @param mixed ...$args
     * @return static
     */
    private function withArgs(int $operation, ...$args)
    {
        // READ_LIST is the only operation with no mandatory argument after the
        // `SyncContextInterface` argument
        if ($operation !== SyncOperation::READ_LIST) {
            array_shift($args);
        }

        if (!$args) {
            return $this->applyFilters([]);
        }

        if (is_array($args[0]) && count($args) === 1) {
            $filters = [];
            $filterKeys = [];
            foreach ($args[0] as $key => $value) {
                if (
                    is_int($key)
                    || Test::isNumericKey($key = trim($key))
                ) {
                    throw new InvalidFilterSignatureException($operation, ...$args);
                }

                $normalised = false;
                if (Regex::match('/^(?:[[:alnum:]]++(?:$|[\s_-]++(?!$)))++$/D', $key)) {
                    $key = Str::snake($key);
                    $normalised = true;
                }

                $filters[$key] = $value = $this->normaliseFilterValue($value);
                unset($filterKeys[$key]);

                if (!$normalised || !(
                    $value === null
                    || is_int($value)
                    || is_string($value)
                    || Arr::ofArrayKey($value, true)
                )) {
                    continue;
                }

                $altKey = substr($key, -3) === '_id'
                    ? substr($key, 0, -3)
                    : $key . '_id';

                if (isset($filters[$altKey])) {
                    continue;
                }

                $filterKeys[$altKey] = $key;
            }

            return $this->applyFilters($filters, $filterKeys);
        }

        if (Arr::ofArrayKey($args)) {
            return $this->applyFilters(['id' => $args]);
        }

        if (Arr::of($args, SyncEntityInterface::class)) {
            foreach ($args as $entity) {
                /** @var SyncEntityInterface $entity */
                $id = $this->normaliseFilterEntity($entity);
                $service = $entity->getService();
                $key = self::$ServiceKeyMap[$service] ??=
                    Str::snake(Get::basename($service));
                $filters[$key][] = $id;
            }

            return $this->withArgs(SyncOperation::READ_LIST, $filters);
        }

        throw new InvalidFilterSignatureException($operation, ...$args);
    }

    /**
     * @param array<string,(int|string|float|bool|null)[]|int|string|float|bool|null> $filters
     * @param array<string,string> $filterKeys
     * @return static
     */
    private function applyFilters(array $filters, array $filterKeys = [])
    {
        return $this
            ->with('Filters', $filters)
            ->with('FilterKeys', $filterKeys);
    }

    /**
     * @param mixed $value
     * @return (int|string|float|bool|null)[]|int|string|float|bool|null
     */
    private function normaliseFilterValue($value)
    {
        if ($value === null || $value === [] || is_scalar($value)) {
            return $value;
        }

        if ($value instanceof DateTimeInterface) {
            return $this->getProvider()->getDateFormatter()->format($value);
        }

        if ($value instanceof SyncEntityInterface) {
            return $this->normaliseFilterEntity($value);
        }

        if (is_array($value)) {
            foreach ($value as &$entry) {
                if ($entry === null || is_scalar($entry)) {
                    continue;
                }

                if ($entry instanceof DateTimeInterface) {
                    $entry = $this->getProvider()->getDateFormatter()->format($entry);
                    continue;
                }

                if ($entry instanceof SyncEntityInterface) {
                    $entry = $this->normaliseFilterEntity($entry);
                    continue;
                }

                throw new InvalidFilterException(sprintf(
                    'Invalid in filter value: %s',
                    Get::type($entry),
                ));
            }

            /** @var (int|string|float|bool|null)[] */
            return $value;
        }

        throw new InvalidFilterException(sprintf(
            'Invalid filter value: %s',
            Get::type($value),
        ));
    }

    /**
     * @return array-key
     */
    private function normaliseFilterEntity(SyncEntityInterface $entity)
    {
        $id = $entity->getId();

        if ($id === null) {
            throw new InvalidFilterException(sprintf(
                '%s has no identifier',
                get_class($entity),
            ));
        }

        if ($entity->getProvider() === $this->Provider) {
            return $id;
        }

        throw new InvalidFilterException(sprintf(
            '%s has a different provider',
            get_class($entity),
        ));
    }

    /**
     * @inheritDoc
     */
    public function getDeferralPolicy(): int
    {
        return $this->DeferralPolicy;
    }

    /**
     * @inheritDoc
     */
    public function withDeferralPolicy(int $policy)
    {
        return $this->with('DeferralPolicy', $policy);
    }

    /**
     * @inheritDoc
     */
    public function getHydrationPolicy(?string $entityType): int
    {
        $depth = count($this->Entities) + 1;

        if ($entityType !== null && $this->EntityHydrationPolicy) {
            $applied = false;
            $flags = 0;
            foreach ($this->EntityHydrationPolicy as $entity => $values) {
                if (!is_a($entity, $entityType, true)) {
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
     * @param int&HydrationPolicy::* $policy
     */
    public function withHydrationPolicy(
        int $policy,
        ?string $entityType = null,
        $depth = null
    ) {
        // @phpstan-ignore smaller.alwaysFalse, booleanAnd.rightAlwaysFalse
        if ($depth !== null && array_filter((array) $depth, fn($depth) => $depth < 1)) {
            throw new LogicException('$depth must be greater than 0');
        }

        $clone = clone $this;
        $clone->applyHydrationPolicy($policy, $entityType, $depth);

        if (
            $this->EntityHydrationPolicy === $clone->EntityHydrationPolicy
            && $this->FallbackHydrationPolicy === $clone->FallbackHydrationPolicy
        ) {
            return $this;
        }

        return $clone;
    }

    /**
     * @param int&HydrationPolicy::* $policy
     * @param class-string<SyncEntityInterface>|null $entityType
     * @param array<int<1,max>>|int<1,max>|null $depth
     */
    private function applyHydrationPolicy(
        int $policy,
        ?string $entityType,
        $depth
    ): void {
        $currentDepth = count($this->Entities);

        if ($entityType === null && $depth === null) {
            $this->EntityHydrationPolicy = [];
            $this->FallbackHydrationPolicy = [0 => $policy];
            return;
        }

        if ($entityType === null) {
            $this->FallbackHydrationPolicy = $this->doApplyHydrationPolicy(
                $policy,
                $depth,
                $currentDepth,
                $this->FallbackHydrationPolicy,
            );
        } else {
            $this->EntityHydrationPolicy +=
                [$entityType => $this->FallbackHydrationPolicy];
        }

        foreach ($this->EntityHydrationPolicy as $entity => &$value) {
            if ($entityType === null || is_a($entity, $entityType, true)) {
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
     * @param int<0,max> $currentDepth
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
    public function withOffline(?bool $offline)
    {
        return $this->with('Offline', $offline);
    }
}
