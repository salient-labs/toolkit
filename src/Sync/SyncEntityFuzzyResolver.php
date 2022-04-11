<?php

declare(strict_types=1);

namespace Lkrms\Sync;

use Lkrms\Console\Console;
use Lkrms\Convert;
use Lkrms\Generate;

/**
 * Uses Levenshtein distances or text similarity to resolve names to entities
 *
 * The default algorithm is
 * {@see SyncEntityFuzzyResolver::ALGORITHM_LEVENSHTEIN}.
 *
 * @package Lkrms
 */
class SyncEntityFuzzyResolver extends SyncEntityResolver
{
    /**
     * Inexpensive, but string length cannot exceed 255 characters, and
     * similar_text() may match substrings better
     */
    const ALGORITHM_LEVENSHTEIN = 0;

    /**
     * Expensive, but strings of any length can be compared
     */
    const ALGORITHM_SIMILAR_TEXT = 1;

    /**
     * @var string|null
     */
    protected $WeightField;

    /**
     * @var array<int,array{0:SyncEntity,1:string}>
     */
    protected $Entities;

    /**
     * @var int
     */
    protected $Algorithm;

    /**
     * @var float
     */
    protected $UncertaintyThreshold;

    private $Cache = [];

    /**
     *
     * @param SyncEntityProvider $entityProvider
     * @param string $nameField
     * @param null|string $weightField If multiple entities are equally similar
     * to a given name, the one with the highest weight is preferred.
     * @param int|null $algorithm Overrides the default string comparison
     * algorithm. Either {@see SyncEntityFuzzyResolver::ALGORITHM_LEVENSHTEIN}
     * or {@see SyncEntityFuzzyResolver::ALGORITHM_SIMILAR_TEXT}.
     * @param float|null $uncertaintyThreshold
     */
    public function __construct(
        SyncEntityProvider $entityProvider,
        string $nameField,
        ?string $weightField,
        int $algorithm = null,
        float $uncertaintyThreshold = null
    )
    {
        parent::__construct($entityProvider, $nameField);
        $this->WeightField          = $weightField;
        $this->Algorithm            = $algorithm;
        $this->UncertaintyThreshold = $uncertaintyThreshold;
    }

    private function loadEntities()
    {
        $nameField = $this->NameField;

        $this->Entities = [];

        foreach ($this->EntityProvider->getList() as $entity)
        {
            $this->Entities[] = [
                $entity,
                Convert::toNormal($entity->$nameField)
            ];
        }
    }

    private function getUncertainty(string $string1, string $string2): float
    {
        switch ($this->Algorithm)
        {
            case self::ALGORITHM_SIMILAR_TEXT:

                return 1 - Generate::textSimilarity($string1, $string2, false);

            case self::ALGORITHM_LEVENSHTEIN:
            default:

                return Generate::textDistance($string1, $string2, false);
        }
    }

    public function getByName(string $name, float & $uncertainty = null): ?SyncEntity
    {
        if (is_null($this->Entities))
        {
            $this->loadEntities();
        }

        $normalised = Convert::toNormal($name);

        if (isset($this->Cache[$normalised]))
        {
            list ($entity, $uncertainty) = $this->Cache[$normalised] ?: [null, null];

            return $entity;
        }

        $uncertainty = null;

        $sort   = $this->Entities;
        $weight = $this->WeightField;
        usort($sort, function ($e1, $e2) use ($normalised, $weight)
        {
            $d1 = $this->getUncertainty($normalised, $e1[1]);
            $d2 = $this->getUncertainty($normalised, $e2[1]);

            if ($d1 == $d2)
            {
                $w1 = $e1[0]->$weight;
                $w2 = $e2[0]->$weight;

                return $w1 == $w2 ? 0 : ($w1 > $w2 ? -1 : 1);
            }
            else
            {
                return $d1 < $d2 ? -1 : 1;
            }
        });
        $cache = $match = reset($sort);

        if ($match !== false)
        {
            $uncertainty = $this->getUncertainty($normalised, $match[1]);

            if (!is_null($this->UncertaintyThreshold) &&
                $uncertainty >= $this->UncertaintyThreshold)
            {
                $nameField = $this->NameField;
                Console::debugOnce(sprintf(
                    "Match with '%s' exceeds uncertainty threshold (%.2f >= %.2f):",
                    $match[0]->$nameField,
                    $uncertainty,
                    $this->UncertaintyThreshold
                ), $name);
                $cache       = $match = false;
                $uncertainty = null;
            }
            else
            {
                $cache = [$match[0], $uncertainty];
            }
        }

        $this->Cache[$normalised] = $cache;

        return $match[0] ?? null;
    }
}

