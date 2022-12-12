<?php declare(strict_types=1);

namespace Lkrms\Support;

use Closure;
use UnexpectedValueException;

/**
 * Creates closures that rearrange arrays
 *
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
     * @param array<int|string,int|string|array<int,int|string>> $keyMap An
     * array that maps input keys to one or more output keys.
     * @param int $conformity One of the {@see ArrayKeyConformity} values. Use
     * `COMPLETE` wherever possible to improve performance.
     * @param int $flags A bitmask of {@see ArrayMapperFlag} values.
     * @return Closure
     * ```php
     * static function (array $in): array
     * ```
     */
    public function getKeyMapClosure(array $keyMap, int $conformity = ArrayKeyConformity::NONE, int $flags = ArrayMapperFlag::ADD_UNMAPPED): Closure
    {
        $sig = implode("\x00", array_map(
            fn($v) => is_array($v) ? implode("\x01", $v) : $v,
            array_merge(
                array_keys($keyMap), array_values($keyMap), [$conformity, $flags]
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
        $allTargetsScalar = count($keyMap) === count($flipped);

        if ($allTargetsScalar && $conformity === ArrayKeyConformity::COMPLETE) {
            $outKeys = array_values($keyMap);
            $closure = static function (array $in) use ($outKeys): array {
                $out = array_combine($outKeys, $in);
                if ($out === false) {
                    throw new UnexpectedValueException('Invalid input array');
                }

                return $out;
            };
        } else {
            $addMissing    = (bool) ($flags & ArrayMapperFlag::ADD_MISSING);
            $requireMapped = (bool) ($flags & ArrayMapperFlag::REQUIRE_MAPPED);

            $closure = static function (array $in) use ($flipped, $addMissing, $requireMapped): array {
                $out = [];
                foreach ($flipped as $outKey => $inKey) {
                    if ($addMissing || array_key_exists($inKey, $in)) {
                        $out[$outKey] = $in[$inKey] ?? null;
                    } elseif ($requireMapped) {
                        throw new UnexpectedValueException("Input array has no data at key '$inKey'");
                    }
                }

                return $out;
            };

            if ($flags & ArrayMapperFlag::ADD_UNMAPPED) {
                // Add unmapped values (`array_diff_key($in, $keyMap...`) that
                // don't conflict with output array keys (`...$flipped`)
                $closure = static function (array $in) use ($keyMap, $flipped, $closure): array {
                    return array_merge(
                        $closure($in),
                        array_diff_key($in, $keyMap, $flipped)
                    );
                };
            }
        }

        if ($flags & ArrayMapperFlag::REMOVE_NULL) {
            $closure = static function (array $in) use ($closure): array {
                return array_filter(
                    $closure($in),
                    function ($v) {return !is_null($v);}
                );
            };
        }

        $this->KeyMapClosures[$sig] = $closure;

        return $closure;
    }
}
