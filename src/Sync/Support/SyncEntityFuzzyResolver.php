<?php declare(strict_types=1);

namespace Lkrms\Sync\Support;

use Lkrms\Contract\IIterable;
use Lkrms\Facade\Compute;
use Lkrms\Facade\Console;
use Lkrms\Facade\Convert;
use Lkrms\Sync\Contract\ISyncEntity;
use Lkrms\Sync\Contract\ISyncEntityProvider;
use Lkrms\Sync\Contract\ISyncEntityResolver;

/**
 * Resolves names to entities using a text similarity algorithm
 *
 * The default algorithm is
 * {@see SyncEntityFuzzyResolver::ALGORITHM_LEVENSHTEIN}.
 *
 * @template TEntity of ISyncEntity
 * @template TList of array|IIterable
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
     * @var ISyncEntityProvider<TEntity,TList>
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
     * @var array<int,array{0:TEntity,1:string}>|null
     */
    private $Entities;

    /**
     * @var int|null
     */
    private $Algorithm;

    /**
     * @var float|null
     */
    private $UncertaintyThreshold;

    private $Cache = [];

    /**
     * @param ISyncEntityProvider<TEntity,TList> $entityProvider
     * @param string|null $weightProperty If multiple entities are equally
     * similar to a given name, the one with the highest weight is preferred.
     * @param int|null $algorithm Overrides the default string comparison
     * algorithm. Either {@see SyncEntityFuzzyResolver::ALGORITHM_LEVENSHTEIN}
     * or {@see SyncEntityFuzzyResolver::ALGORITHM_SIMILAR_TEXT}.
     */
    public function __construct(ISyncEntityProvider $entityProvider, string $nameProperty, ?string $weightProperty, ?int $algorithm = null, ?float $uncertaintyThreshold = null)
    {
        $this->EntityProvider       = $entityProvider;
        $this->NameProperty         = $nameProperty;
        $this->WeightProperty       = $weightProperty;
        $this->Algorithm            = $algorithm;
        $this->UncertaintyThreshold = $uncertaintyThreshold;
    }

    private function loadEntities()
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
            default:
                return Compute::textDistance($string1, $string2, false);
        }
    }

    private function compareUncertainty(string $name, array $e1, array $e2): int
    {
        return $this->getUncertainty($name, $e1[1]) <=> $this->getUncertainty($name, $e2[1]);
    }

    public function getByName(string $name, float &$uncertainty = null): ?ISyncEntity
    {
        if (is_null($this->Entities)) {
            $this->loadEntities();
        }

        $_name = Convert::toNormal($name);
        if (array_key_exists($_name, $this->Cache)) {
            [$entity, $uncertainty] = $this->Cache[$_name] ?: [null, null];

            return $entity;
        }

        $uncertainty = null;

        $sort = $this->Entities;
        usort($sort, fn($e1, $e2) => $this->compareUncertainty($_name, $e1, $e2)
            ?: ($e2[0]->{$this->WeightProperty} <=> $e1[0]->{$this->WeightProperty}));
        $cache = $match = reset($sort);

        if ($match !== false) {
            $uncertainty = $this->getUncertainty($_name, $match[1]);
            if (!is_null($this->UncertaintyThreshold) && $uncertainty >= $this->UncertaintyThreshold) {
                Console::debugOnce(sprintf(
                    "Match with '%s' exceeds uncertainty threshold (%.2f >= %.2f):",
                    $match[0]->{$this->NameProperty},
                    $uncertainty,
                    $this->UncertaintyThreshold
                ), $name);
                $cache       = $match = false;
                $uncertainty = null;
            } else {
                $cache       = [$match[0], $uncertainty];
            }
        }

        $this->Cache[$_name] = $cache;

        return $match[0] ?? null;
    }
}
