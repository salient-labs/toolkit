<?php declare(strict_types=1);

namespace Salient\Sync\Support;

use Lkrms\Support\Catalog\TextComparisonAlgorithm;
use Lkrms\Support\Catalog\TextComparisonAlgorithm as Algorithm;
use Lkrms\Support\Catalog\TextComparisonFlag;
use Lkrms\Support\Catalog\TextComparisonFlag as Flag;
use Salient\Core\Utility\Str;
use Salient\Sync\Contract\ISyncEntity;
use Salient\Sync\Contract\ISyncEntityProvider;
use Salient\Sync\Contract\ISyncEntityResolver;
use Closure;
use LogicException;

/**
 * Resolves a name to an entity using one or more text comparison algorithms
 *
 * @template TEntity of ISyncEntity
 *
 * @implements ISyncEntityResolver<TEntity>
 */
final class SyncEntityFuzzyResolver implements ISyncEntityResolver
{
    private const ALGORITHMS = [
        Algorithm::SAME,
        Algorithm::CONTAINS,
        Algorithm::LEVENSHTEIN,
        Algorithm::SIMILAR_TEXT,
        Algorithm::NGRAM_SIMILARITY,
        Algorithm::NGRAM_INTERSECTION,
    ];

    /**
     * @var ISyncEntityProvider<TEntity>
     */
    private $EntityProvider;

    /**
     * @var string|Closure(TEntity): (string|null)
     */
    private $NameProperty;

    /**
     * @var int-mask-of<Algorithm::*>
     */
    private $Algorithm;

    /**
     * @var array<Algorithm::*,float>|float|null
     */
    private $UncertaintyThreshold;

    /**
     * @var string|null
     */
    private $WeightProperty;

    /**
     * @var bool
     */
    private $RequireOneMatch;

    /**
     * [ [ Entity, normalised name, weight ] ]
     *
     * @var array<array{TEntity,string,mixed|null}>|null
     */
    private $Entities;

    /**
     * Query => [ entity, uncertainty ]
     *
     * @var array<string,array{TEntity|null,float|null}>
     */
    private $Cache = [];

    /**
     * Creates a new SyncEntityFuzzyResolver object
     *
     * @param ISyncEntityProvider<TEntity> $entityProvider
     * @param int-mask-of<TextComparisonAlgorithm::*|TextComparisonFlag::*> $algorithm
     * @param array<TextComparisonAlgorithm::*,float>|float|null $uncertaintyThreshold
     * @param string|null $weightProperty If multiple entities are equally
     * similar to a given name, the one with the highest weight is preferred.
     */
    public function __construct(
        ISyncEntityProvider $entityProvider,
        ?string $nameProperty = null,
        int $algorithm =
            TextComparisonAlgorithm::SAME
            | TextComparisonAlgorithm::CONTAINS
            | TextComparisonAlgorithm::NGRAM_SIMILARITY
            | TextComparisonFlag::NORMALISE,
        $uncertaintyThreshold = null,
        ?string $weightProperty = null,
        bool $requireOneMatch = false
    ) {
        // Reduce $uncertaintyThreshold to values that will actually be applied
        if (is_array($uncertaintyThreshold)) {
            $uncertaintyThreshold = array_intersect_key(
                $uncertaintyThreshold,
                array_flip(self::ALGORITHMS),
            );
            foreach (array_keys($uncertaintyThreshold) as $key) {
                if (!($algorithm & $key)) {
                    unset($uncertaintyThreshold[$key]);
                }
            }
            if (!$uncertaintyThreshold) {
                $uncertaintyThreshold = null;
            }
        }

        // Throw an exception if one match is required but the list of potential
        // matches is never narrowed
        if (
            $requireOneMatch &&
            $uncertaintyThreshold === null &&
            !($algorithm & (Algorithm::SAME | Algorithm::CONTAINS))
        ) {
            throw new LogicException(
                '$requireOneMatch cannot be true when $uncertaintyThreshold is null'
            );
        }

        $this->EntityProvider = $entityProvider;
        $this->NameProperty =
            $nameProperty === null
                ? SyncIntrospector::get($entityProvider->entity())->getGetNameClosure()
                : $nameProperty;
        $this->Algorithm = $algorithm;
        $this->UncertaintyThreshold = $uncertaintyThreshold;
        $this->WeightProperty = $weightProperty;
        $this->RequireOneMatch = $requireOneMatch;
    }

    /**
     * @inheritDoc
     */
    public function getByName(string $name, float &$uncertainty = null): ?ISyncEntity
    {
        if ($this->Entities === null) {
            $this->loadEntities();
        }

        if (!$this->Entities) {
            $uncertainty = null;
            return null;
        }

        $name =
            $this->Algorithm & Flag::NORMALISE
                ? Str::normalise($name)
                : $name;

        if (isset($this->Cache[$name])) {
            [$entity, $uncertainty] = $this->Cache[$name];
            return $entity;
        }

        /** @var array<array{TEntity,string,mixed|null,...}> */
        $entries = $this->Entities;
        $applied = 0;

        foreach (self::ALGORITHMS as $algorithm) {
            if (!($this->Algorithm & $algorithm)) {
                continue;
            }

            $threshold =
                $this->RequireOneMatch &&
                $algorithm & (Algorithm::SAME | Algorithm::CONTAINS)
                    ? 1.0
                    : ($this->UncertaintyThreshold === null
                        ? null
                        : (is_array($this->UncertaintyThreshold)
                            ? ($this->UncertaintyThreshold[$algorithm] ?? null)
                            : $this->UncertaintyThreshold));

            // Skip this algorithm if it would achieve nothing
            if ($this->RequireOneMatch && $threshold === null) {
                continue;
            }

            $next = [];
            foreach ($entries as $entry) {
                $entityName = $entry[1];
                $entityUncertainty = $this->getUncertainty(
                    $name,
                    $entityName,
                    $algorithm,
                );
                if ($threshold !== null && (
                    ($threshold !== 0.0 && $entityUncertainty >= $threshold) ||
                    ($threshold === 0.0 && $entityUncertainty > $threshold)
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

        usort(
            $entries,
            function ($e1, $e2) use ($applied) {
                // Uncertainty values, most recent to least recent, ascending
                for ($i = $applied + 2; $i >= 3; $i--) {
                    $result = $e1[$i] <=> $e2[$i];
                    if ($result) {
                        return $result;
                    }
                }
                // Weight, descending
                return $e2[2] <=> $e1[2];
            }
        );

        return $this->cacheResult($name, $entries[0], $uncertainty);
    }

    private function loadEntities(): void
    {
        $this->Entities = [];
        foreach ($this->EntityProvider->getList() as $entity) {
            $name =
                is_string($this->NameProperty)
                    ? $entity->{$this->NameProperty}
                    : ($this->NameProperty)($entity);
            $this->Entities[] = [
                $entity,
                $this->Algorithm & Flag::NORMALISE
                    ? Str::normalise($name)
                    : $name,
                $this->WeightProperty === null
                    ? 0
                    : $entity->{$this->WeightProperty},
            ];
        }
    }

    /**
     * @param Algorithm::SAME|Algorithm::CONTAINS|Algorithm::LEVENSHTEIN|Algorithm::SIMILAR_TEXT|Algorithm::NGRAM_SIMILARITY|Algorithm::NGRAM_INTERSECTION $algorithm
     */
    private function getUncertainty(string $string1, string $string2, $algorithm): float
    {
        switch ($algorithm) {
            case Algorithm::SAME:
                return $string1 === $string2
                    ? 0.0
                    : 1.0;

            case Algorithm::CONTAINS:
                return
                    strpos($string2, $string1) !== false ||
                    strpos($string1, $string2) !== false
                        ? 0.0
                        : 1.0;

            case Algorithm::LEVENSHTEIN:
                return Str::distance($string1, $string2, false);

            case Algorithm::SIMILAR_TEXT:
                return 1 - Str::similarity($string1, $string2, false);

            case Algorithm::NGRAM_SIMILARITY:
                return 1 - Str::ngramSimilarity($string1, $string2, false);

            case Algorithm::NGRAM_INTERSECTION:
                return 1 - Str::ngramIntersection($string1, $string2, false);

            default:
                throw new LogicException(sprintf(
                    'Invalid algorithm: %d',
                    $this->Algorithm,
                ));
        }
    }

    /**
     * @param array{TEntity,string,mixed|null,...} $entry
     * @return TEntity
     */
    private function cacheResult(string $name, ?array $entry, ?float &$uncertainty): ?ISyncEntity
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
