<?php declare(strict_types=1);

namespace Salient\Core;

use Salient\Core\Contract\ConvertibleEnumerationInterface;
use Salient\Core\Utility\Inflect;
use Salient\Core\Utility\Str;
use LogicException;
use ReflectionClass;

/**
 * Base class for enumerations that use reflection to map constants to and from
 * their names
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

    private static function loadMaps(): void
    {
        $constants = (new ReflectionClass(static::class))->getReflectionConstants();

        $valueMap = [];
        $nameMap = [];
        foreach ($constants as $constant) {
            if (!$constant->isPublic()) {
                continue;
            }

            $name = $constant->getName();
            $value = $constant->getValue();

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
            throw new LogicException(
                sprintf(
                    'Public constants are not unique: %s',
                    static::class,
                )
            );
        }

        // Add UPPER_CASE names to $valueMap if not already present
        $valueMap += array_change_key_case($valueMap, \CASE_UPPER);

        self::$ValueMaps[static::class] = $valueMap;
        self::$NameMaps[static::class] = $nameMap;
    }

    /**
     * @inheritDoc
     */
    final public static function fromName(string $name)
    {
        if (!isset(self::$ValueMaps[static::class])) {
            // @codeCoverageIgnoreStart
            self::loadMaps();
            // @codeCoverageIgnoreEnd
        }

        $value = self::$ValueMaps[static::class][$name]
            ?? self::$ValueMaps[static::class][Str::upper($name)]
            ?? null;

        if ($value === null) {
            throw new LogicException(
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
        if (!isset(self::$ValueMaps[static::class])) {
            // @codeCoverageIgnoreStart
            self::loadMaps();
            // @codeCoverageIgnoreEnd
        }

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
            throw new LogicException(
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
        if (!isset(self::$NameMaps[static::class])) {
            // @codeCoverageIgnoreStart
            self::loadMaps();
            // @codeCoverageIgnoreEnd
        }

        $name = self::$NameMaps[static::class][$value] ?? null;

        if ($name === null) {
            throw new LogicException(
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
        if (!isset(self::$NameMaps[static::class])) {
            // @codeCoverageIgnoreStart
            self::loadMaps();
            // @codeCoverageIgnoreEnd
        }

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
            throw new LogicException(
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
        if (!isset(self::$ValueMaps[static::class])) {
            // @codeCoverageIgnoreStart
            self::loadMaps();
            // @codeCoverageIgnoreEnd
        }

        return self::$ValueMaps[static::class];
    }

    /**
     * @inheritDoc
     */
    final public static function hasValue($value): bool
    {
        if (!isset(self::$NameMaps[static::class])) {
            // @codeCoverageIgnoreStart
            self::loadMaps();
            // @codeCoverageIgnoreEnd
        }

        return isset(self::$NameMaps[static::class][$value]);
    }
}
