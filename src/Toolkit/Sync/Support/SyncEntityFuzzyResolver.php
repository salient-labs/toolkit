<?php declare(strict_types=1);

namespace Salient\Sync\Support;

use Salient\Contract\Sync\SyncEntityInterface;
use Salient\Contract\Sync\SyncEntityProviderInterface;
use Salient\Contract\Sync\SyncEntityResolverInterface;
use Salient\Utility\Reflect;
use Salient\Utility\Str;
use Closure;
use InvalidArgumentException;

/**
 * Resolves a name to an entity by identifying the closest match
 *
 * Entities are retrieved when {@see SyncEntityFuzzyResolver::getByName()} is
 * first called, and are held by the instance until it is destroyed.
 *
 * @api
 *
 * @template TEntity of SyncEntityInterface
 *
 * @implements SyncEntityResolverInterface<TEntity>
 */
final class SyncEntityFuzzyResolver implements SyncEntityResolverInterface
{
    public const DEFAULT_FLAGS =
        SyncEntityFuzzyResolver::ALGORITHM_SAME
        | SyncEntityFuzzyResolver::ALGORITHM_CONTAINS
        | SyncEntityFuzzyResolver::ALGORITHM_NGRAM_SIMILARITY
        | SyncEntityFuzzyResolver::NORMALISE;

    private const FUZZY_ALGORITHMS =
        self::ALGORITHM_LEVENSHTEIN
        | self::ALGORITHM_SIMILAR_TEXT
        | self::ALGORITHM_NGRAM_SIMILARITY
        | self::ALGORITHM_NGRAM_INTERSECTION;

    /** @var SyncEntityProviderInterface<TEntity> */
    private SyncEntityProviderInterface $EntityProvider;
    private bool $HasNameProperty;
    private string $NameProperty;
    /** @var Closure(TEntity): string */
    private Closure $GetNameClosure;
    /** @var int-mask-of<self::*> */
    private int $Flags;
    /** @var array<self::ALGORITHM_*,float|null> */
    private array $UncertaintyThreshold;
    private bool $HasWeightProperty;
    private string $WeightProperty;
    /** @var (Closure(TEntity): (int|float))|null */
    private ?Closure $GetWeightClosure;
    private bool $RequireOneMatch;

    /**
     * [ [ Entity, normalised name, weight ], ... ]
     *
     * @var array<array{TEntity,string,int|float}>
     */
    private array $Entities;

    /**
     * Normalised name => [ entity, uncertainty ]
     *
     * @var array<string,array{TEntity,float}|array{null,null}>
     */
    private array $Cache = [];

    /**
     * @api
     *
     * @param SyncEntityProviderInterface<TEntity> $entityProvider
     * @param (Closure(TEntity): string)|string|null $nameProperty If `null`,
     * entity names are taken from {@see SyncEntityInterface::getName()}.
     * @param int-mask-of<SyncEntityFuzzyResolver::*> $flags
     * @param array<SyncEntityFuzzyResolver::ALGORITHM_*,float>|float|null $uncertaintyThreshold If
     * the uncertainty of a match for a given name is greater than or equal to
     * this value (between `0.0` and `1.0`), the entity is not returned.
     * @param (Closure(TEntity): (int|float))|string|null $weightProperty If
     * multiple entities are equally similar to a given name, the one with the
     * greatest weight (highest value) is preferred.
     */
    public function __construct(
        SyncEntityProviderInterface $entityProvider,
        $nameProperty = null,
        int $flags = SyncEntityFuzzyResolver::DEFAULT_FLAGS,
        $uncertaintyThreshold = null,
        $weightProperty = null,
        bool $requireOneMatch = false
    ) {
        $algorithms = $this->getAlgorithms($flags);
        if (!$algorithms) {
            throw new InvalidArgumentException('At least one algorithm flag must be set');
        }
        if (!is_array($uncertaintyThreshold)) {
            $uncertaintyThreshold = array_fill_keys($algorithms, $uncertaintyThreshold);
        }
        $this->UncertaintyThreshold = [];
        foreach ($algorithms as $algorithm) {
            $threshold = $uncertaintyThreshold[$algorithm] ?? null;
            if ($requireOneMatch && $threshold === null) {
                if ($algorithm & self::FUZZY_ALGORITHMS) {
                    throw new InvalidArgumentException(sprintf(
                        'Invalid $uncertaintyThreshold for %s when $requireOneMatch is true',
                        Reflect::getConstantName(self::class, $algorithm),
                    ));
                } else {
                    $threshold = 1.0;
                }
            }
            $this->UncertaintyThreshold[$algorithm] = $threshold;
        }

        $this->EntityProvider = $entityProvider;
        if (is_string($nameProperty)) {
            $this->HasNameProperty = true;
            $this->NameProperty = $nameProperty;
        } else {
            $this->HasNameProperty = false;
            $this->GetNameClosure = $nameProperty
                ?? SyncIntrospector::get($entityProvider->entity())
                    ->getGetNameClosure();
        }
        $this->Flags = $flags;
        if (is_string($weightProperty)) {
            $this->HasWeightProperty = true;
            $this->WeightProperty = $weightProperty;
        } else {
            $this->HasWeightProperty = false;
            $this->GetWeightClosure = $weightProperty;
        }
        $this->RequireOneMatch = $requireOneMatch;
    }

    /**
     * @inheritDoc
     */
    public function getByName(string $name, ?float &$uncertainty = null): ?SyncEntityInterface
    {
        $this->Entities ??= $this->getEntities();

        if (!$this->Entities) {
            $uncertainty = null;
            return null;
        }

        if ($this->Flags & self::NORMALISE) {
            $name = Str::normalise($name);
        }

        if (isset($this->Cache[$name])) {
            [$entity, $uncertainty] = $this->Cache[$name];
            return $entity;
        }

        $entries = $this->Entities;
        $applied = 0;
        foreach ($this->UncertaintyThreshold as $algorithm => $threshold) {
            $next = [];
            foreach ($entries as $entry) {
                $entityName = $entry[1];
                $entityUncertainty = $this->getUncertainty($name, $entityName, $algorithm);
                if ($threshold !== null && (
                    ($threshold !== 0.0 && $entityUncertainty >= $threshold)
                    || ($threshold === 0.0 && $entityUncertainty > $threshold)
                )) {
                    continue;
                }
                $entry[] = $entityUncertainty;
                $next[] = $entry;
            }

            // If there are no matching entities, try again with the next
            // algorithm
            if (!$next) {
                continue;
            }

            // If there is one matching entity, return it
            if (count($next) === 1) {
                return $this->cacheResult($name, $next[0], $uncertainty);
            }

            // Otherwise, narrow the list of potential matches and continue
            $entries = $next;
            $applied++;
        }

        if (!$applied || $this->RequireOneMatch) {
            return $this->cacheResult($name, null, $uncertainty);
        }

        // Sort entries by:
        // - uncertainty values, most recent to least recent, ascending
        // - weight, descending
        /** @var array<array{TEntity,string,int|float,float,...<float>}> $entries */
        usort(
            $entries,
            function ($e1, $e2) use ($applied) {
                for ($i = $applied + 2; $i > 2; $i--) {
                    if ($result = $e1[$i] <=> $e2[$i]) {
                        return $result;
                    }
                }
                return $e2[2] <=> $e1[2];
            }
        );

        // Return the best match
        return $this->cacheResult($name, $entries[0], $uncertainty);
    }

    /**
     * @return list<self::ALGORITHM_*>
     */
    private function getAlgorithms(int $flags): array
    {
        foreach ([
            self::ALGORITHM_SAME,
            self::ALGORITHM_CONTAINS,
            self::ALGORITHM_LEVENSHTEIN,
            self::ALGORITHM_SIMILAR_TEXT,
            self::ALGORITHM_NGRAM_SIMILARITY,
            self::ALGORITHM_NGRAM_INTERSECTION,
        ] as $algorithm) {
            if ($flags & $algorithm) {
                $algorithms[] = $algorithm;
            }
        }

        return $algorithms ?? [];
    }

    /**
     * @return array<array{TEntity,string,int|float}>
     */
    private function getEntities(): array
    {
        foreach ($this->EntityProvider->getList() as $entity) {
            if ($this->HasNameProperty) {
                $name = $entity->{$this->NameProperty} ?? null;
                if (!is_string($name)) {
                    continue;
                }
            } else {
                $name = ($this->GetNameClosure)($entity);
            }
            if ($this->Flags & self::NORMALISE) {
                $name = Str::normalise($name);
            }
            if ($this->HasWeightProperty) {
                $weight = $entity->{$this->WeightProperty} ?? null;
                if (!(is_int($weight) || is_float($weight))) {
                    $weight = \PHP_INT_MIN;
                }
            } elseif ($this->GetWeightClosure) {
                $weight = ($this->GetWeightClosure)($entity);
            } else {
                $weight = \PHP_INT_MIN;
            }
            $entities[] = [$entity, $name, $weight];
        }

        return $entities ?? [];
    }

    /**
     * @param self::ALGORITHM_* $algorithm
     */
    private function getUncertainty(string $string1, string $string2, int $algorithm): float
    {
        switch ($algorithm) {
            case self::ALGORITHM_SAME:
                return $string1 === $string2
                    ? 0.0
                    : 1.0;

            case self::ALGORITHM_CONTAINS:
                return strpos($string2, $string1) !== false
                    || strpos($string1, $string2) !== false
                        ? 0.0
                        : 1.0;

            case self::ALGORITHM_LEVENSHTEIN:
                return Str::distance($string1, $string2);

            case self::ALGORITHM_SIMILAR_TEXT:
                return 1.0 - Str::similarity($string1, $string2);

            case self::ALGORITHM_NGRAM_SIMILARITY:
                return 1.0 - Str::ngramSimilarity($string1, $string2);

            case self::ALGORITHM_NGRAM_INTERSECTION:
                return 1.0 - Str::ngramIntersection($string1, $string2);
        }
    }

    /**
     * @param array{TEntity,string,int|float,float,...<float>}|null $entry
     * @return TEntity|null
     */
    private function cacheResult(string $name, ?array $entry, ?float &$uncertainty): ?SyncEntityInterface
    {
        if ($entry === null) {
            $uncertainty = null;
            $this->Cache[$name] = [null, null];
            return null;
        }

        $uncertainty = array_pop($entry);
        $this->Cache[$name] = [$entry[0], $uncertainty];
        return $entry[0];
    }
}
