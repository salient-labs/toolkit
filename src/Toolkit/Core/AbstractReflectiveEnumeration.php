<?php declare(strict_types=1);

namespace Salient\Core;

use Salient\Contract\Core\ConvertibleEnumerationInterface;
use Salient\Utility\Inflect;
use Salient\Utility\Str;
use InvalidArgumentException;
use LogicException;

/**
 * Base class for enumerations that use reflection to map constants to and from
 * their names
 *
 * @api
 *
 * @template TValue of array-key
 *
 * @extends AbstractEnumeration<TValue>
 * @implements ConvertibleEnumerationInterface<TValue>
 */
abstract class AbstractReflectiveEnumeration extends AbstractEnumeration implements ConvertibleEnumerationInterface
{
    /**
     * Class name => [ constant value => name ]
     *
     * @var array<string,array<TValue,string>>
     */
    private static $NameMaps = [];

    /**
     * Class name => [ constant name => value ]
     *
     * @var array<string,array<string,TValue>>
     */
    private static $ValueMaps = [];

    /**
     * @inheritDoc
     */
    final public static function fromName(string $name)
    {
        self::checkMaps();

        $value = self::$ValueMaps[static::class][$name]
            ?? self::$ValueMaps[static::class][Str::upper($name)]
            ?? null;

        if ($value === null) {
            throw new InvalidArgumentException(
                sprintf('Invalid name: %s', $name)
            );
        }

        /** @var TValue */
        return $value;
    }

    /**
     * @inheritDoc
     */
    final public static function fromNames(array $names): array
    {
        self::checkMaps();

        $invalid = [];
        foreach ($names as $name) {
            $value = self::$ValueMaps[static::class][$name]
                ?? self::$ValueMaps[static::class][Str::upper($name)]
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

        /** @var TValue[] */
        return $values ?? [];
    }

    /**
     * @inheritDoc
     */
    final public static function toName($value): string
    {
        self::checkMaps();

        $name = self::$NameMaps[static::class][$value] ?? null;

        if ($name === null) {
            throw new InvalidArgumentException(
                sprintf('Invalid value: %s', $value)
            );
        }

        return $name;
    }

    /**
     * @inheritDoc
     */
    final public static function toNames(array $values): array
    {
        self::checkMaps();

        $invalid = [];
        foreach ($values as $value) {
            $name = self::$NameMaps[static::class][$value] ?? null;

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
        self::checkMaps();

        return self::$ValueMaps[static::class];
    }

    /**
     * @inheritDoc
     */
    final public static function hasValue($value): bool
    {
        self::checkMaps();

        return isset(self::$NameMaps[static::class][$value]);
    }

    private static function checkMaps(): void
    {
        if (isset(self::$NameMaps[static::class])) {
            return;
        }

        $valueMap = [];
        $nameMap = [];
        /** @var mixed $value */
        foreach (self::constants() as $name => $value) {
            if (!is_int($value) && !is_string($value)) {
                throw new LogicException(sprintf(
                    'Public constant is not of type int|string: %s::%s',
                    static::class,
                    $name,
                ));
            }

            $valueMap[$name] = $value;
            $nameMap[$value] = $name;
        }

        if (!$valueMap) {
            self::$ValueMaps[static::class] = [];
            self::$NameMaps[static::class] = [];
            return;
        }

        if (count($valueMap) !== count($nameMap)) {
            throw new LogicException(sprintf(
                'Public constants do not have unique values: %s',
                static::class,
            ));
        }

        // Add UPPER_CASE names to $valueMap if not already present
        $valueMap += array_change_key_case($valueMap, \CASE_UPPER);

        self::$ValueMaps[static::class] = $valueMap;
        self::$NameMaps[static::class] = $nameMap;
    }
}
