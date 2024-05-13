<?php declare(strict_types=1);

namespace Salient\Core;

use ReflectionClass;

/**
 * Base class for enumerations and dictionaries
 *
 * @internal
 *
 * @template TValue
 */
abstract class AbstractCatalog
{
    /** @var array<class-string<static>,array<string,TValue>> */
    private static array $Constants = [];
    /** @var array<class-string<static>,array<TValue&array-key,string[]|string>> */
    private static array $ConstantNames = [];

    /**
     * @return array<string,TValue>
     */
    protected static function constants(): array
    {
        return self::$Constants[static::class] ??= self::getConstants();
    }

    /**
     * @return array<TValue&array-key,string[]|string>
     */
    protected static function constantNames(): array
    {
        return self::$ConstantNames[static::class] ??= self::getConstantNames();
    }

    /**
     * @return array<string,TValue>
     */
    private static function getConstants(): array
    {
        $_constants = (new ReflectionClass(static::class))->getReflectionConstants();
        foreach ($_constants as $_constant) {
            if ($_constant->isPublic()) {
                $constants[$_constant->getName()] = $_constant->getValue();
            }
        }
        return $constants ?? [];
    }

    /**
     * @return array<TValue&array-key,string[]|string>
     */
    private static function getConstantNames(): array
    {
        foreach (self::constants() as $name => $value) {
            if (!(is_int($value) || is_string($value))) {
                continue;
            }
            if (!isset($names[$value])) {
                $names[$value] = $name;
                continue;
            }
            if (!is_array($names[$value])) {
                $names[$value] = (array) $names[$value];
            }
            $names[$value][] = $name;
        }

        return $names ?? [];
    }

    final private function __construct() {}
}
