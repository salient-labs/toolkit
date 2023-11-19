<?php declare(strict_types=1);

namespace Lkrms\Support;

use Lkrms\Exception\UnexpectedValueException;
use Lkrms\Support\Catalog\ArrayKeyConformity;
use Lkrms\Support\Catalog\ArrayMapperFlag;
use Closure;

/**
 * Creates closures that rearrange arrays
 */
final class ArrayMapper
{
    /**
     * @var Closure[]
     */
    private $KeyMapClosures = [];

    /**
     * Get a closure to move array values from one set of keys to another
     *
     * By default, the closure:
     * - populates an "output" array with values mapped from an "input" array
     * - ignores missing values (maps for which there are no input values)
     * - discards unmapped values (input values for which there are no maps)
     * - keeps `null` values in the output array
     *
     * Provide a bitmask of {@see ArrayMapperFlag} values to modify this
     * behaviour.
     *
     * @param array<array-key,array-key|array-key[]> $keyMap An array that maps
     * input keys to one or more output keys.
     * @param ArrayKeyConformity::* $conformity Use `COMPLETE` wherever possible
     * to improve performance.
     * @param int-mask-of<ArrayMapperFlag::*> $flags
     * @return Closure(array<array-key,mixed>): array<array-key,mixed>
     */
    public function getKeyMapClosure(
        array $keyMap,
        $conformity = ArrayKeyConformity::NONE,
        int $flags = ArrayMapperFlag::ADD_UNMAPPED
    ): Closure {
        $sig = implode("\x01", array_map(
            fn($keyOrKeys) =>
                is_array($keyOrKeys)
                    ? implode("\x02", $keyOrKeys)
                    : $keyOrKeys,
            array_merge(
                array_keys($keyMap),
                array_values($keyMap),
                [$conformity, $flags]
            )
        ));

        if ($closure = $this->KeyMapClosures[$sig] ?? null) {
            return $closure;
        }

        $flipped = [];
        foreach ($keyMap as $inKey => $out) {
            if (is_array($out)) {
                foreach ($out as $outKey) {
                    $flipped[$outKey] = $inKey;
                }
                continue;
            }
            $flipped[$out] = $inKey;
        }

        if (count($keyMap) === count($flipped) &&
                $conformity === ArrayKeyConformity::COMPLETE) {
            $outKeys = array_keys($flipped);
            $closure =
                static function (array $in) use ($outKeys): array {
                    $out = @array_combine($outKeys, $in);
                    if ($out === false) {
                        throw new UnexpectedValueException('Invalid input array');
                    }
                    return $out;
                };
        } else {
            $addMissing = (bool) ($flags & ArrayMapperFlag::ADD_MISSING);
            $requireMapped = (bool) ($flags & ArrayMapperFlag::REQUIRE_MAPPED);
            $closure =
                static function (array $in) use ($flipped, $addMissing, $requireMapped): array {
                    foreach ($flipped as $outKey => $inKey) {
                        if ($addMissing || array_key_exists($inKey, $in)) {
                            $out[$outKey] = $in[$inKey] ?? null;
                        } elseif ($requireMapped) {
                            throw new UnexpectedValueException(sprintf('No data at input key: %s', $inKey));
                        }
                    }
                    return $out ?? [];
                };

            // Add unmapped values that don't conflict with output array keys
            if ($flags & ArrayMapperFlag::ADD_UNMAPPED) {
                $closure =
                    static function (array $in) use ($keyMap, $flipped, $closure): array {
                        return array_merge(
                            $closure($in),
                            array_diff_key($in, $keyMap, $flipped)
                        );
                    };
            }
        }

        if ($flags & ArrayMapperFlag::REMOVE_NULL) {
            $closure =
                static function (array $in) use ($closure): array {
                    return array_filter(
                        $closure($in),
                        function ($value) { return $value !== null; }
                    );
                };
        }

        return $this->KeyMapClosures[$sig] = $closure;
    }
}
