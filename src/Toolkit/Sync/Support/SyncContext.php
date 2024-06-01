<?php declare(strict_types=1);

namespace Salient\Sync\Support;

use Salient\Contract\Sync\DeferralPolicy;
use Salient\Contract\Sync\HydrationPolicy;
use Salient\Contract\Sync\SyncContextInterface;
use Salient\Contract\Sync\SyncEntityInterface;
use Salient\Contract\Sync\SyncOperation;
use Salient\Contract\Sync\SyncProviderInterface;
use Salient\Core\Exception\InvalidArgumentException;
use Salient\Core\Exception\LogicException;
use Salient\Core\Utility\Arr;
use Salient\Core\Utility\Get;
use Salient\Core\Utility\Pcre;
use Salient\Core\Utility\Str;
use Salient\Core\Utility\Test;
use Salient\Core\ProviderContext;
use Salient\Sync\Exception\SyncEntityRecursionException;
use Salient\Sync\Exception\SyncInvalidFilterException;
use Salient\Sync\Exception\SyncInvalidFilterSignatureException;
use DateTimeInterface;

/**
 * The context within which sync entities are instantiated by a provider
 *
 * @extends ProviderContext<SyncProviderInterface,SyncEntityInterface>
 */
final class SyncContext extends ProviderContext implements SyncContextInterface
{
    private const INTEGER = 1;
    private const STRING = 2;
    private const LIST = 8;

    /** @var array<string,(int|string|float|bool|null)[]|int|string|float|bool|null> */
    protected array $Filters = [];
    /** @var array<string,string> */
    protected array $FilterKeys = [];
    /** @var (callable(SyncContextInterface, ?bool &$returnEmpty, array{}|null &$empty): void)|null */
    protected $FilterPolicyCallback;
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
    protected ?SyncEntityInterface $LastRecursedInto = null;
    /** @var array<class-string,string> */
    private static array $ServiceKeyMap;

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
    public function withFilter($operation, ...$args)
    {
        // READ_LIST is the only operation with no mandatory argument after the
        // `SyncContextInterface` argument
        if ($operation !== SyncOperation::READ_LIST) {
            array_shift($args);
        }

        if (!$args) {
            return $this->applyFilters();
        }

        if (is_array($args[0]) && count($args) === 1) {
            $filters = [];
            $filterKeys = [];
            foreach ($args[0] as $key => $value) {
                if (
                    is_int($key)
                    || ($key = trim($key)) === ''
                    || Test::isNumericKey($key)
                ) {
                    throw new SyncInvalidFilterSignatureException($operation, ...$args);
                }

                $normalised = false;
                if (Pcre::match('/^([[:alnum:]]++($|[ _-]++(?!$)))++$/D', $key)) {
                    $key = Str::toSnakeCase($key);
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
                $id = $entity->id();
                if ($id === null) {
                    throw new SyncInvalidFilterException(sprintf(
                        '%s has no identifier',
                        get_class($entity),
                    ));
                }

                if ($entity->getProvider() !== $this->Provider) {
                    throw new SyncInvalidFilterException(sprintf(
                        '%s does not have same provider',
                        get_class($entity),
                    ));
                }

                $service = $entity->getService();
                $key = self::$ServiceKeyMap[$service]
                    ??= Str::toSnakeCase(Get::basename($service));
                $filters[$key][] = $entity->id();
            }

            return $this->applyFilters($filters);
        }

        throw new SyncInvalidFilterSignatureException($operation, ...$args);
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

        if ($this->EntityHydrationPolicy === $clone->EntityHydrationPolicy
                && $this->FallbackHydrationPolicy === $clone->FallbackHydrationPolicy) {
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
            $this->LastRecursedInto
            && $this->LastRecursedInto === end($this->Stack)
        ) {
            throw new SyncEntityRecursionException(sprintf(
                'Circular reference detected: %s',
                $this->LastRecursedInto->uri($this->Provider->store()),
            ));
        }
    }

    /**
     * @inheritDoc
     */
    public function getFilter(?string $key = null, bool $orValue = true)
    {
        if ($key === null) {
            return $this->Filters;
        }

        return $this->doGetFilter($key, $orValue);
    }

    /**
     * @inheritDoc
     */
    public function getFilterInt(string $key, bool $orValue = true): ?int
    {
        return $this->doGetFilter($key, $orValue, false, self::INTEGER);
    }

    /**
     * @inheritDoc
     */
    public function getFilterString(string $key, bool $orValue = true): ?string
    {
        return $this->doGetFilter($key, $orValue, false, self::STRING);
    }

    /**
     * @inheritDoc
     */
    public function getFilterArrayKey(string $key, bool $orValue = true)
    {
        return $this->doGetFilter($key, $orValue, false, self::INTEGER | self::STRING);
    }

    /**
     * @inheritDoc
     */
    public function getFilterIntList(string $key, bool $orValue = true): ?array
    {
        return $this->doGetFilter($key, $orValue, false, self::INTEGER | self::LIST);
    }

    /**
     * @inheritDoc
     */
    public function getFilterStringList(string $key, bool $orValue = true): ?array
    {
        return $this->doGetFilter($key, $orValue, false, self::STRING | self::LIST);
    }

    /**
     * @inheritDoc
     */
    public function getFilterArrayKeyList(string $key, bool $orValue = true): ?array
    {
        return $this->doGetFilter($key, $orValue, false, self::INTEGER | self::STRING | self::LIST);
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
    public function claimFilterInt(string $key, bool $orValue = true): ?int
    {
        return $this->doGetFilter($key, $orValue, true, self::INTEGER);
    }

    /**
     * @inheritDoc
     */
    public function claimFilterString(string $key, bool $orValue = true): ?string
    {
        return $this->doGetFilter($key, $orValue, true, self::STRING);
    }

    /**
     * @inheritDoc
     */
    public function claimFilterArrayKey(string $key, bool $orValue = true)
    {
        return $this->doGetFilter($key, $orValue, true, self::INTEGER | self::STRING);
    }

    /**
     * @inheritDoc
     */
    public function claimFilterIntList(string $key, bool $orValue = true): ?array
    {
        return $this->doGetFilter($key, $orValue, true, self::INTEGER | self::LIST);
    }

    /**
     * @inheritDoc
     */
    public function claimFilterStringList(string $key, bool $orValue = true): ?array
    {
        return $this->doGetFilter($key, $orValue, true, self::STRING | self::LIST);
    }

    /**
     * @inheritDoc
     */
    public function claimFilterArrayKeyList(string $key, bool $orValue = true): ?array
    {
        return $this->doGetFilter($key, $orValue, true, self::INTEGER | self::STRING | self::LIST);
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
     * @param array<string,(int|string|float|bool|null)[]|int|string|float|bool|null> $filters
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
     * @param mixed $value
     * @return (int|string|float|bool|null)[]|int|string|float|bool|null
     */
    private function normaliseFilterValue($value)
    {
        if ($value === null || $value === [] || is_scalar($value)) {
            return $value;
        }

        if ($value instanceof DateTimeInterface) {
            return $value->format(DateTimeInterface::ATOM);
        }

        if ($value instanceof SyncEntityInterface) {
            return $this->normaliseFilterEntity($value);
        }

        if (is_array($value)) {
            $invalid = false;
            foreach ($value as &$entry) {
                if ($entry === null || is_scalar($entry)) {
                    continue;
                }

                if ($entry instanceof DateTimeInterface) {
                    $entry = $entry->format(DateTimeInterface::ATOM);
                    continue;
                }

                if ($entry instanceof SyncEntityInterface) {
                    $entry = $this->normaliseFilterEntity($entry);
                    continue;
                }

                $invalid = true;
                break;
            }

            if (!$invalid) {
                return $value;
            }
        }

        throw new InvalidArgumentException('Invalid filter value');
    }

    /**
     * @return array-key
     */
    private function normaliseFilterEntity(SyncEntityInterface $entity)
    {
        $id = $entity->id();

        if ($id === null) {
            throw new InvalidArgumentException(sprintf(
                '%s has no identifier',
                get_class($entity),
            ));
        }

        if ($entity->getProvider() === $this->Provider) {
            return $id;
        }

        throw new InvalidArgumentException(sprintf(
            '%s has a different provider',
            get_class($entity),
        ));
    }

    /**
     * @template TType of int-mask-of<self::*>|null
     *
     * @param (int|null)&TType $type
     * @return (
     *     TType is 1
     *     ? int
     *     : (TType is 2
     *         ? string
     *         : (TType is 3
     *             ? int|string
     *             : (TType is 9
     *                 ? int[]
     *                 : (TType is 10
     *                     ? string[]
     *                     : (TType is 11
     *                         ? (int|string)[]
     *                         : (int|string|float|bool|null)[]|int|string|float|bool
     *                     )
     *                 )
     *             )
     *         )
     *     )
     * )|null
     */
    private function doGetFilter(string $key, bool $orValue, bool $claim = false, $type = null)
    {
        if (
            !array_key_exists($key, $this->Filters)
            && !array_key_exists($key = Str::toSnakeCase($key), $this->Filters)
        ) {
            if (!array_key_exists($key, $this->FilterKeys)) {
                return $orValue
                    ? $this->checkFilterValue($this->getValue($key), $type, 'context')
                    : null;
            }
            [$key, $altKey] = [$this->FilterKeys[$key], $key];
            if ($claim) {
                unset($this->FilterKeys[$altKey]);
            }
        } elseif ($claim && $this->FilterKeys) {
            $this->FilterKeys = array_diff($this->FilterKeys, [$key]);
        }

        $value = $this->checkFilterValue($this->Filters[$key], $type);

        if ($claim) {
            unset($this->Filters[$key]);
        }

        return $value;
    }

    /**
     * @template TValue of (int|string|float|bool|null)[]|int|string|float|bool|null
     * @template TType of int-mask-of<self::*>|null
     *
     * @param TValue $value
     * @param (int|null)&TType $type
     * @return (
     *     TValue is null
     *     ? null
     *     : (TType is null
     *         ? TValue
     *         : (TType is 1
     *             ? int
     *             : (TType is 2
     *                 ? string
     *                 : (TType is 3
     *                     ? int|string
     *                     : (TType is 9
     *                         ? int[]
     *                         : (TType is 10
     *                             ? string[]
     *                             : (TType is 11
     *                                 ? (int|string)[]
     *                                 : (TType is 8
     *                                     ? (int|string|float|bool|null)[]
     *                                     : int|string|float|bool
     *                                 )
     *                             )
     *                         )
     *                     )
     *                 )
     *             )
     *         )
     *     )
     * )|null
     */
    private function checkFilterValue($value, $type, string $scope = 'filter')
    {
        if ($value === null || $type === null) {
            return $value;
        }

        if ($type & self::LIST) {
            /** @var int-mask-of<self::INTEGER|self::STRING> */
            $type = $type & ~self::LIST;
            foreach (Arr::wrap($value) as $key => $value) {
                $list[$key] = $this->checkFilterValue($value, $type, $scope);
            }

            return $list ?? [];
        }

        $value = Arr::unwrap($value, 1);

        if (is_int($value)) {
            if ($type & self::INTEGER) {
                return $value;
            }
        } elseif (is_string($value)) {
            if ($type & self::STRING) {
                return $value;
            }
        } elseif (is_float($value) || is_bool($value)) {
            if (!($type & (self::INTEGER | self::STRING))) {
                return $value;
            }
        }

        throw new SyncInvalidFilterException(sprintf('Invalid %s value', $scope));
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
            $this->EntityHydrationPolicy
                += [$entity => $this->FallbackHydrationPolicy];
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
}
