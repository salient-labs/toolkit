<?php declare(strict_types=1);

namespace Salient\Core;

use Salient\Contract\Catalog\ListConformity;
use Salient\Contract\Core\Pipeline\ArrayMapperInterface;
use Salient\Core\Exception\InvalidDataException;
use Salient\Utility\Arr;
use ValueError;

/**
 * Moves array values from one set of keys to another
 *
 * @api
 */
final class ArrayMapper implements ArrayMapperInterface
{
    /** @var array-key[]|null */
    private ?array $OutputKeys;
    /** @var array<array-key,array-key> */
    private array $OutputMap;
    private bool $RemoveNull;
    private bool $AddUnmapped;
    private bool $RequireMapped;
    private bool $AddMissing;
    /** @var array<array-key,true> */
    private array $InputKeyIndex;

    /**
     * Creates a new ArrayMapper object
     *
     * By default, an array mapper:
     *
     * - ignores missing values (maps where the input array has no data)
     * - preserves unmapped values (input that isn't mapped to a different key)
     * - does not remove `null` values from the output array
     *
     * @param array<array-key,array-key|array-key[]> $keyMap An array that maps
     * input keys to one or more output keys.
     * @param ListConformity::* $conformity Use {@see ListConformity::COMPLETE}
     * wherever possible to improve performance.
     * @param int-mask-of<ArrayMapper::*> $flags
     */
    public function __construct(
        array $keyMap,
        int $conformity = ListConformity::NONE,
        int $flags = ArrayMapper::ADD_UNMAPPED
    ) {
        $outKeyMap = [];
        foreach ($keyMap as $inKey => $outKeys) {
            foreach ((array) $outKeys as $outKey) {
                $outKeyMap[$outKey] = $inKey;
            }
        }

        $this->RemoveNull = (bool) ($flags & self::REMOVE_NULL);

        if (
            $conformity === ListConformity::COMPLETE
            && count($keyMap) === count($outKeyMap)
        ) {
            $this->OutputKeys = array_keys($outKeyMap);
            return;
        }

        $this->OutputKeys = null;
        $this->OutputMap = $outKeyMap;
        $this->AddUnmapped = (bool) ($flags & self::ADD_UNMAPPED);
        $this->RequireMapped = (bool) ($flags & self::REQUIRE_MAPPED);
        $this->AddMissing = !$this->RequireMapped && $flags & self::ADD_MISSING;

        if ($this->AddUnmapped) {
            $this->InputKeyIndex = array_fill_keys(array_keys($keyMap), true);
        }
    }

    /**
     * @inheritDoc
     */
    public function map(array $in): array
    {
        if ($this->OutputKeys !== null) {
            try {
                $out = Arr::combine($this->OutputKeys, $in);
            } catch (ValueError $ex) {
                throw new InvalidDataException('Invalid input array', 0, $ex);
            }

            return $this->RemoveNull ? Arr::whereNotNull($out) : $out;
        }

        $out = [];
        foreach ($this->OutputMap as $outKey => $inKey) {
            if (array_key_exists($inKey, $in)) {
                $out[$outKey] = $in[$inKey];
            } elseif ($this->AddMissing) {
                $out[$outKey] = null;
            } elseif ($this->RequireMapped) {
                throw new InvalidDataException(sprintf(
                    'Input key not found: %s',
                    $inKey,
                ));
            }
        }

        if ($this->AddUnmapped) {
            $out += array_diff_key($in, $this->InputKeyIndex);
        }

        return $this->RemoveNull ? Arr::whereNotNull($out) : $out;
    }
}
