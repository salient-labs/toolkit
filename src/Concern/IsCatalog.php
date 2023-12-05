<?php declare(strict_types=1);

namespace Lkrms\Concern;

use ReflectionClass;

/**
 * Has public constants with values of a given type, and cannot be instantiated
 *
 * @template TValue
 */
trait IsCatalog
{
    /**
     * @var array<class-string<static>,array<string,TValue>>
     */
    private static array $Constants = [];

    /**
     * @return array<string,TValue>
     */
    protected static function constants(): array
    {
        return self::$Constants[static::class]
            ?? (self::$Constants[static::class] = self::getConstants());
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

    final private function __construct() {}
}
