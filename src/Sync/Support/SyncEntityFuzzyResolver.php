<?php declare(strict_types=1);

namespace Lkrms\Sync\Support;

use Lkrms\Support\Catalog\TextComparisonAlgorithm as Algorithm;
use Lkrms\Sync\Contract\ISyncEntity;
use Lkrms\Sync\Contract\ISyncEntityProvider;
use Lkrms\Sync\Contract\ISyncEntityResolver;
use Lkrms\Utility\Compute;
use Lkrms\Utility\Convert;
use LogicException;

/**
 * Resolves a name to an entity using one or more text comparison algorithms
 *
 * @template TEntity of ISyncEntity
 * @implements ISyncEntityResolver<TEntity>
 */
final class SyncEntityFuzzyResolver implements ISyncEntityResolver
{
    /**
     * @var ISyncEntityProvider<TEntity>
     */
    private $EntityProvider;

    /**
     * @var string
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
     * @param int-mask-of<Algorithm::*> $algorithm
     * @param array<Algorithm::*,float>|float|null $uncertaintyThreshold
     * @param string|null $weightProperty If multiple entities are equally
     * similar to a given name, the one with the highest weight is preferred.
     */
    public function __construct(
        ISyncEntityProvider $entityProvider,
        string $nameProperty,
        int $algorithm = Algorithm::ALL,
        $uncertaintyThreshold = null,
        ?string $weightProperty = null,
        bool $requireOneMatch = false
    ) {
        $this->EntityProvider = $entityProvider;
        $this->NameProperty = $nameProperty;
        $this->Algorithm = $algorithm;
        $this->UncertaintyThreshold = $uncertaintyThreshold;
        $this->WeightProperty = $weightProperty;
        $this->RequireOneMatch = $requireOneMatch;
    }

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
            $this->Algorithm & Algorithm::NORMALISE
                ? Convert::toNormal($name)
                : $name;

        if (isset($this->Cache[$name])) {
            [$entity, $uncertainty] = $this->Cache[$name];
            return $entity;
        }

        /**
         * @var array<array{TEntity,string,mixed|null,...<int,float>}>
         */
        $entries = $this->Entities;
        $applied = 0;

        foreach ([
            Algorithm::SAME,
            Algorithm::CONTAINS,
            Algorithm::LEVENSHTEIN,
            Algorithm::SIMILAR_TEXT,
            Algorithm::NGRAM_SIMILARITY,
            Algorithm::NGRAM_INTERSECTION,
        ] as $algorithm) {
            if (!($this->Algorithm & $algorithm)) {
                continue;
            }

            $threshold =
                $this->UncertaintyThreshold === null
                    ? null
                    : (is_array($this->UncertaintyThreshold)
                        ? ($this->UncertaintyThreshold[$algorithm] ?? null)
                        : $this->UncertaintyThreshold);

            $sort = [];
            foreach ($entries as $entry) {
                $entityName = $entry[1];
                $entityUncertainty = $this->getUncertainty(
                    $name,
                    $entityName,
                    $algorithm,
                );
                if ($threshold !== null && $entityUncertainty >= $threshold) {
                    continue;
                }
                $entry[] = $entityUncertainty;
                $sort[] = $entry;
            }

            // If there are no matching entities, try again with the next
            // algorithm
            if (!$sort) {
                continue;
            }

            // If there is one matching entity, return it
            if (count($sort) === 1) {
                return $this->cacheResult($name, $sort[0], $uncertainty);
            }

            // Otherwise, narrow the list of potential matches and continue
            $entries = $sort;
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
            $this->Entities[] = [
                $entity,
                $this->Algorithm & Algorithm::NORMALISE
                    ? Convert::toNormal($entity->{$this->NameProperty})
                    : $entity->{$this->NameProperty},
                $this->WeightProperty === null
                    ? 0
                    : $entity->{$this->WeightProperty},
            ];
        }
    }

    /**
     * @param Algorithm::* $algorithm
     */
    private function getUncertainty(string $string1, string $string2, $algorithm): float
    {
        switch ($algorithm) {
            case Algorithm::SAME:
                return $string1 === $string2 ? 0.0 : 1.0;

            case Algorithm::CONTAINS:
                return
                    strpos($string2, $string1) !== false ||
                    strpos($string1, $string2) !== false
                        ? 0.0
                        : 1.0;

            case Algorithm::LEVENSHTEIN:
                return Compute::textDistance($string1, $string2, false);

            case Algorithm::SIMILAR_TEXT:
                return 1 - Compute::textSimilarity($string1, $string2, false);

            case Algorithm::NGRAM_SIMILARITY:
                return 1 - Compute::ngramSimilarity($string1, $string2, false);

            case Algorithm::NGRAM_INTERSECTION:
                return 1 - Compute::ngramIntersection($string1, $string2, false);

            default:
                throw new LogicException(sprintf(
                    'Invalid algorithm: %d',
                    $this->Algorithm,
                ));
        }
    }

    /**
     * @param array{TEntity,string,mixed|null,...<int,float>} $entry
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
