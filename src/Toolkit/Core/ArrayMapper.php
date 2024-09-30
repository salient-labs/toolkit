<?php declare(strict_types=1);

namespace Salient\Core;

use Salient\Contract\Core\ArrayMapperInterface;
use Salient\Contract\Core\ListConformity;
use Salient\Utility\Arr;
use InvalidArgumentException;

/**
 * Moves array values from one set of keys to another
 *
 * @api
 */
final class ArrayMapper
{
    /**
     * Output key => input key
     *
     * @var array<array-key,array-key>
     */
    private array $OutputMap = [];

    /**
     * Output keys
     *
     * @var array-key[]|null
     */
    private ?array $OutputKeys = null;

    /**
     * Input key => output key
     *
     * @var array<array-key,array-key>
     */
    private array $KeyMap;

    private bool $RemoveNull;
    private bool $AddUnmapped;
    private bool $AddMissing;
    private bool $RequireMapped;

    /**
     * Creates a new ArrayMapper object
     *
     * By default, an array mapper:
     *
     * - populates an "output" array with values mapped from an "input" array
     * - ignores missing values (maps for which there are no input values)
     * - preserves unmapped values (input values for which there are no maps)
     * - keeps `null` values in the output array
     *
     * Provide a bitmask of {@see ArrayMapperInterface} values to modify this
     * behaviour.
     *
     * @param array<array-key,array-key|array-key[]> $keyMap An array that maps
     * input keys to one or more output keys.
     * @param ListConformity::* $conformity Use {@see ListConformity::COMPLETE}
     * wherever possible to improve performance.
     * @param int-mask-of<ArrayMapperInterface::*> $flags
     */
    public function __construct(
        array $keyMap,
        $conformity = ListConformity::NONE,
        int $flags = ArrayMapperInterface::ADD_UNMAPPED
    ) {
        foreach ($keyMap as $inKey => $outKey) {
            foreach ((array) $outKey as $outKey) {
                $this->OutputMap[$outKey] = $inKey;
            }
        }

        $this->RemoveNull = (bool) ($flags & ArrayMapperInterface::REMOVE_NULL);

        if (
            count($keyMap) === count($this->OutputMap)
            && $conformity === ListConformity::COMPLETE
        ) {
            $this->OutputKeys = array_keys($this->OutputMap);
            return;
        }

        $this->KeyMap = $keyMap;
        $this->AddUnmapped = (bool) ($flags & ArrayMapperInterface::ADD_UNMAPPED);
        $this->AddMissing = (bool) ($flags & ArrayMapperInterface::ADD_MISSING);
        $this->RequireMapped = (bool) ($flags & ArrayMapperInterface::REQUIRE_MAPPED);
    }

    /**
     * Map an input array to an output array
     *
     * @param array<array-key,mixed> $in
     * @return array<array-key,mixed>
     */
    public function map(array $in): array
    {
        if ($this->OutputKeys !== null) {
            $out = @array_combine($this->OutputKeys, $in);

            if ($out === false) {
                throw new InvalidArgumentException('Invalid input array');
            }

            return $this->RemoveNull
                ? Arr::whereNotNull($out)
                : $out;
        }

        $out = [];
        foreach ($this->OutputMap as $outKey => $inKey) {
            if ($this->AddMissing || array_key_exists($inKey, $in)) {
                $out[$outKey] = $in[$inKey] ?? null;
                continue;
            }
            if ($this->RequireMapped) {
                throw new InvalidArgumentException(sprintf('No data at input key: %s', $inKey));
            }
        }

        // Add unmapped values that don't conflict with output array keys
        if ($this->AddUnmapped) {
            $out = array_merge(
                $out,
                array_diff_key($in, $this->KeyMap, $this->OutputMap)
            );
        }

        return $this->RemoveNull
            ? Arr::whereNotNull($out)
            : $out;
    }
}
