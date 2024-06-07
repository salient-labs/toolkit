<?php declare(strict_types=1);

namespace Salient\Tests\Utility\Reflect;

use ArrayAccess;
use Countable;

/**
 * MyClassWithUnionsAndIntersections
 */
class MyClassWithUnionsAndIntersections extends MyClass
{
    /**
     * @param mixed $mixed
     * @param Countable&ArrayAccess<array-key,mixed> $intersection
     * @param mixed[]|MyClass $optionalArrayUnion
     */
    public function MyMethod(
        $mixed,
        ?int $nullableInt,
        string $string,
        Countable&ArrayAccess $intersection,
        MyBaseClass $class,
        ?MyClass $nullableClass,
        ?MyClass &$nullableClassByRef,
        ?MyClass $nullableAndOptionalClass = null,
        string $optionalString = MyClass::MY_CONSTANT,
        string|MyClass $union = SELF::MY_CONSTANT,
        string|MyClass|null $nullableUnion = 'literal',
        array|MyClass $optionalArrayUnion = ['key' => 'value'],
        string|MyClass|null &$nullableUnionByRef = null,
        string &...$variadicByRef
    ): MyClass|string|null {
        return null;
    }
}
