<?php declare(strict_types=1);

namespace Salient\Tests\Reflection;

use ArrayAccess;
use Countable;

/**
 * MyClassWithDnfTypes
 */
class MyClassWithDnfTypes extends MyClass
{
    /**
     * @param mixed $mixed
     * @param Countable&ArrayAccess<array-key,mixed> $intersection
     * @param (MyClass&Countable)|(MyClass&ArrayAccess<array-key,mixed>) $dnfByRef
     * @param mixed[]|MyClass $optionalArrayUnion
     * @param string|MyClass|(Countable&ArrayAccess<array-key,mixed>) $dnf
     * @param string|MyClass|(Countable&ArrayAccess<array-key,mixed>)|null $nullableDnf
     * @param mixed[]|MyClass|(Countable&ArrayAccess<array-key,mixed>) $optionalArrayDnf
     * @param string|MyClass|(Countable&ArrayAccess<array-key,mixed>)|null $nullableDnfByRef
     */
    public function MyMethod(
        $mixed,
        null $null,
        ?int $nullableInt,
        string $string,
        Countable&ArrayAccess $intersection,
        MyBaseClass $class,
        ?MyClass $nullableClass,
        ?MyClass &$nullableClassByRef,
        (MyClass&Countable)|(MyClass&ArrayAccess) &$dnfByRef,
        ?MyClass $nullableAndOptionalClass = null,
        string $optionalString = MyClass::MY_CONSTANT,
        string|MyClass $union = SELF::MY_CONSTANT,
        string|MyClass|null $nullableUnion = 'literal',
        array|MyClass $optionalArrayUnion = ['key' => 'value'],
        string|MyClass|null &$nullableUnionByRef = null,
        string|MyClass|(Countable&ArrayAccess) $dnf = SELF::MY_CONSTANT,
        string|MyClass|(Countable&ArrayAccess)|null $nullableDnf = 'literal',
        array|MyClass|(Countable&ArrayAccess) $optionalArrayDnf = ['key' => 'value'],
        string|MyClass|(Countable&ArrayAccess)|null &$nullableDnfByRef = null,
        string &...$variadicByRef
    ): MyClass|string|null {
        return null;
    }
}
