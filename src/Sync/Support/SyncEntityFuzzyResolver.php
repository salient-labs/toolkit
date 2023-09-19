<?php declare(strict_types=1);

namespace Lkrms\Sync\Support;

use Lkrms\Facade\Compute;
use Lkrms\Facade\Console;
use Lkrms\Sync\Contract\ISyncEntity;
use Lkrms\Sync\Contract\ISyncEntityProvider;
use Lkrms\Sync\Contract\ISyncEntityResolver;
use Lkrms\Utility\Convert;
use LogicException;

/**
 * Resolves names to entities using a text similarity algorithm
 *
 * @template TEntity of ISyncEntity
 * @implements ISyncEntityResolver<TEntity>
 */
final class SyncEntityFuzzyResolver implements ISyncEntityResolver
{
    /**
     * Inexpensive, but string length cannot exceed 255 characters
     *
     * {@see SyncEntityFuzzyResolver::ALGORITHM_SIMILAR_TEXT} may match
     * substrings better.
     */
    public const ALGORITHM_LEVENSHTEIN = 0;

    /**
     * Expensive, but strings of any length can be compared
     */
    public const ALGORITHM_SIMILAR_TEXT = 1;

    /**
     * @var ISyncEntityProvider<TEntity>
     */
    private $EntityProvider;

    /**
     * @var string
     */
    private $NameProperty;

    /**
     * @var string|null
     */
    private $WeightProperty;

    /**
     * [ [ Entity, normalised name ] ]
     *
     * @var array<array{TEntity,string}>|null
     */
    private $Entities;

    /**
     * @var int
     */
    private $Algorithm;

    /**
     * @var float|null
     */
    private $UncertaintyThreshold;

    /**
     * Query => ( [ entity, uncertainty ] | false )
     *
     * @var array<string,array{TEntity,float}|false>
     */
    private $Cache = [];

    /**
     * Creates a new SyncEntityFuzzyResolver object
     *
     * @param ISyncEntityProvider<TEntity> $entityProvider
     * @param string|null $weightProperty If multiple entities are equally
     * similar to a given name, the one with the highest weight is preferred.
     * @param SyncEntityFuzzyResolver::* $algorithm
     */
    public function __construct(
        ISyncEntityProvider $entityProvider,
        string $nameProperty,
        ?string $weightProperty,
        int $algorithm = SyncEntityFuzzyResolver::ALGORITHM_LEVENSHTEIN,
        ?float $uncertaintyThreshold = null
    ) {
        $this->EntityProvider = $entityProvider;
        $this->NameProperty = $nameProperty;
        $this->WeightProperty = $weightProperty;
        $this->Algorithm = $algorithm;
        $this->UncertaintyThreshold = $uncertaintyThreshold;
    }

    private function loadEntities(): void
    {
        $this->Entities = [];
        foreach ($this->EntityProvider->getList() as $entity) {
            $this->Entities[] = [
                $entity,
                Convert::toNormal($entity->{$this->NameProperty})
            ];
        }
    }

    private function getUncertainty(string $string1, string $string2): float
    {
        switch ($this->Algorithm) {
            case self::ALGORITHM_SIMILAR_TEXT:
                return 1 - Compute::textSimilarity($string1, $string2, false);

            case self::ALGORITHM_LEVENSHTEIN:
                return Compute::textDistance($string1, $string2, false);

            default:
                throw new LogicException(sprintf('Invalid algorithm: %d', $this->Algorithm));
        }
    }

    /**
     * @param array{TEntity,string} $e1
     * @param array{TEntity,string} $e2
     */
    private function compareUncertainty(string $name, array $e1, array $e2): int
    {
        return $this->getUncertainty($name, $e1[1]) <=>
            $this->getUncertainty($name, $e2[1]);
    }

    public function getByName(string $name, float &$uncertainty = null): ?ISyncEntity
    {
        if ($this->Entities === null) {
            $this->loadEntities();
        }

        $_name = Convert::toNormal($name);
        if (array_key_exists($_name, $this->Cache)) {
            [$entity, $uncertainty] = $this->Cache[$_name] ?: [null, null];
            return $entity;
        }

        $uncertainty = null;

        $sort = $this->Entities;
        usort(
            $sort,
            fn($e1, $e2) =>
                $this->compareUncertainty($_name, $e1, $e2)
                    ?: ($e2[0]->{$this->WeightProperty} <=> $e1[0]->{$this->WeightProperty})
        );
        $match = reset($sort);

        if ($match === false) {
            $this->Cache[$_name] = false;
            return null;
        }

        $uncertainty = $this->getUncertainty($_name, $match[1]);

        if ($this->UncertaintyThreshold === null ||
                $uncertainty < $this->UncertaintyThreshold) {
            $this->Cache[$_name] = [$match[0], $uncertainty];
            return $match[0];
        }

        Console::debugOnce(sprintf(
            'Match exceeds uncertainty threshold (%.2f >= %.2f):',
            $uncertainty,
            $this->UncertaintyThreshold,
        ), sprintf(
            '%s (query: %s)',
            $match[0]->{$this->NameProperty},
            $name,
        ));

        $uncertainty = null;
        $this->Cache[$_name] = false;
        return null;
    }
}
