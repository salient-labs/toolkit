<?php declare(strict_types=1);

namespace Salient\Core;

use Salient\Contract\Core\ConvertibleEnumerationInterface;
use Salient\Utility\Inflect;
use Salient\Utility\Str;
use InvalidArgumentException;

/**
 * Base class for enumerations that use static arrays to map constants to and
 * from their names
 *
 * @api
 *
 * @template TValue of array-key
 *
 * @extends AbstractEnumeration<TValue>
 * @implements ConvertibleEnumerationInterface<TValue>
 */
abstract class AbstractConvertibleEnumeration extends AbstractEnumeration implements ConvertibleEnumerationInterface
{
    /**
     * An array that maps values to names
     *
     * @var array<TValue,string>
     */
    protected static $NameMap = [];

    /**
     * An array that maps UPPERCASE NAMES to values
     *
     * @var array<string,TValue>
     */
    protected static $ValueMap = [];

    /**
     * @inheritDoc
     */
    final public static function fromName(string $name)
    {
        $value = static::$ValueMap[$name]
            ?? static::$ValueMap[Str::upper($name)]
            ?? null;

        if ($value === null) {
            throw new InvalidArgumentException(
                sprintf('Argument #1 ($name) is invalid: %s', $name)
            );
        }

        return $value;
    }

    /**
     * @inheritDoc
     */
    final public static function fromNames(array $names): array
    {
        $invalid = [];
        foreach ($names as $name) {
            $value = static::$ValueMap[$name]
                ?? static::$ValueMap[Str::upper($name)]
                ?? null;

            if ($value === null) {
                $invalid[] = $name;
                continue;
            }

            $values[] = $value;
        }

        if ($invalid) {
            throw new InvalidArgumentException(
                Inflect::format($invalid, 'Invalid {{#:name}}: %s', implode(',', $invalid))
            );
        }

        return $values ?? [];
    }

    /**
     * @inheritDoc
     */
    final public static function toName($value): string
    {
        $name = static::$NameMap[$value] ?? null;

        if ($name === null) {
            throw new InvalidArgumentException(
                sprintf('Argument #1 ($value) is invalid: %s', $value)
            );
        }

        return $name;
    }

    /**
     * @inheritDoc
     */
    final public static function toNames(array $values): array
    {
        $invalid = [];
        foreach ($values as $value) {
            $name = static::$NameMap[$value] ?? null;

            if ($name === null) {
                $invalid[] = $value;
                continue;
            }

            $names[] = $name;
        }

        if ($invalid) {
            throw new InvalidArgumentException(
                Inflect::format($invalid, 'Invalid {{#:value}}: %s', implode(',', $invalid))
            );
        }

        return $names ?? [];
    }

    /**
     * @inheritDoc
     */
    final public static function cases(): array
    {
        return static::$ValueMap;
    }

    /**
     * @inheritDoc
     */
    final public static function hasValue($value): bool
    {
        return isset(static::$NameMap[$value]);
    }
}
