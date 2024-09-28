<?php declare(strict_types=1);

namespace Salient\Tests\Utility\Reflect;

use Salient\Tests\Reflection\MyBaseClass;
use Salient\Tests\Reflection\MyClass;
use Salient\Tests\Reflection\MyClassWithDnfTypes;
use Salient\Tests\Reflection\MyClassWithUnionsAndIntersections;
use Salient\Tests\Reflection\MyDict;
use Salient\Tests\Reflection\MyEnum;
use Salient\Tests\Reflection\MyInterface;
use Salient\Tests\Reflection\MyTrait;
use Salient\Tests\TestCase;
use Salient\Utility\Internal\NamedType;
use Salient\Utility\Reflect;
use Generator;
use InvalidArgumentException;
use ReflectionClass;
use ReflectionClassConstant;
use ReflectionFunctionAbstract;
use ReflectionMethod;
use ReflectionParameter;
use ReflectionProperty;
use Throwable;

/**
 * @covers \Salient\Utility\Reflect
 * @covers \Salient\Utility\Internal\NamedType
 */
final class ReflectTest extends TestCase
{
    public function testGetNames(): void
    {
        $this->assertSame([
            'Salient\Tests\Reflection\MyClass',
            'Salient\Tests\Reflection\MyInterface',
            'Salient\Tests\Reflection\MyTrait',
            'MY_CONSTANT',
            'MyDocumentedMethod',
            'parent',
            'MyDocumentedProperty',
        ], Reflect::getNames([
            new ReflectionClass(MyClass::class),
            new ReflectionClass(MyInterface::class),
            new ReflectionClass(MyTrait::class),
            new ReflectionClassConstant(MyClass::class, 'MY_CONSTANT'),
            new ReflectionMethod(MyClass::class, 'MyDocumentedMethod'),
            new ReflectionParameter([MyClass::class, '__construct'], 'parent'),
            new ReflectionProperty(MyClass::class, 'MyDocumentedProperty'),
        ]));
    }

    public function testGetAllProperties(): void
    {
        $this->assertSame([
            'Id',
            'AltId',
            'Name',
            'Parent',
            'AltParent',
            'MyPrivateProperty2',
            'MyDocumentedProperty',
            'MyPrivateProperty1',
        ], Reflect::getNames(
            Reflect::getAllProperties(new ReflectionClass(MyClass::class))
        ));
    }

    /**
     * @dataProvider getAcceptedTypesProvider
     *
     * @param array<string[]|string>|string $expected
     * @param ReflectionFunctionAbstract|callable $function
     */
    public function testGetAcceptedTypes(
        $expected,
        $function,
        bool $discardBuiltin = false
    ): void {
        $this->maybeExpectException($expected);
        $this->assertSame($expected, Reflect::getAcceptedTypes($function, $discardBuiltin));
    }

    /**
     * @return Generator<array{array<string[]|string>|string,ReflectionFunctionAbstract|callable,2?:bool}>
     */
    public static function getAcceptedTypesProvider(): Generator
    {
        yield from [
            [
                InvalidArgumentException::class . ',$function has no parameter at position 0',
                fn() => null,
            ],
            [
                [],
                fn($mixed) => null,
            ],
            [
                ['int', 'null'],
                fn(?int $nullableInt) => null,
            ],
            [
                [],
                fn(?int $nullableInt) => null,
                true,
            ],
            [
                ['string'],
                fn(string $string) => null,
            ],
            [
                [],
                fn(string $string) => null,
                true,
            ],
            [
                [MyBaseClass::class],
                fn(MyBaseClass $class) => null,
                true,
            ],
            [
                [MyClass::class],
                fn(?MyClass $nullableClass) => null,
                true,
            ],
            [
                [MyClass::class],
                fn(?MyClass &$nullableClassByRef) => null,
                true,
            ],
            [
                [MyClass::class],
                fn(?MyClass $nullableAndOptionalClass = null) => null,
                true,
            ],
            [
                [ReflectionClass::class],
                [Reflect::class, 'getBaseClass'],
            ],
        ];

        if (\PHP_VERSION_ID >= 80100) {
            yield from require self::getFixturesPath(MyClass::class) . '/callbacksWithUnionsAndIntersections.php';
        }

        if (\PHP_VERSION_ID >= 80200) {
            yield from require self::getFixturesPath(MyClass::class) . '/callbacksWithDnfTypes.php';
        }
    }

    /**
     * @dataProvider getTypesProvider
     *
     * @param array<array<string[]|string>> $normalisedExpected
     * @param array<string[]> $allTypesExpected
     */
    public function testGetTypes(
        array $normalisedExpected,
        array $allTypesExpected,
        string $class,
        string $method
    ): void {
        $method = new ReflectionMethod($class, $method);
        $normalised = [];
        $allTypes = [];
        $allTypeNames = [];
        foreach ($method->getParameters() as $param) {
            $types = [];
            foreach (Reflect::normaliseType($param->getType()) as $type) {
                if (is_array($type)) {
                    $types[] = Reflect::getNames($type);
                    continue;
                }
                $types[] = $type->getName();
            }
            $normalised[] = $types;
            $allTypes[] = Reflect::getNames(Reflect::getTypes($param->getType()));
            $allTypeNames[] = Reflect::getTypeNames($param->getType());
        }

        $this->assertSame($normalisedExpected, $normalised);
        $this->assertSame($allTypesExpected, $allTypes);
        $this->assertSame($allTypesExpected, $allTypeNames);
    }

    /**
     * @return Generator<string,array{array<array<string[]|string>>,array<string[]>,string,string}>
     */
    public static function getTypesProvider(): Generator
    {
        $types = [
            [],
            ['int', 'null'],
            ['string'],
            ['Salient\Tests\Reflection\MyClass', 'null'],
            ['Salient\Tests\Reflection\MyClass', 'null'],
        ];

        yield 'MyClass::__construct()' => [
            $types,
            $types,
            MyClass::class,
            '__construct',
        ];

        if (\PHP_VERSION_ID >= 80100) {
            $allTypes = [
                [],
                ['int', 'null'],
                ['string'],
                ['Countable', 'ArrayAccess'],
                ['Salient\Tests\Reflection\MyBaseClass'],
                ['Salient\Tests\Reflection\MyClass', 'null'],
                ['Salient\Tests\Reflection\MyClass', 'null'],
                ['Salient\Tests\Reflection\MyClass', 'null'],
                ['string'],
                ['Salient\Tests\Reflection\MyClass', 'string'],
                ['Salient\Tests\Reflection\MyClass', 'string', 'null'],
                ['Salient\Tests\Reflection\MyClass', 'array'],
                ['Salient\Tests\Reflection\MyClass', 'string', 'null'],
                ['string'],
            ];
            $types = $allTypes;
            $types[3] = [['Countable', 'ArrayAccess']];

            yield 'MyClassWithUnionsAndIntersections::MyMethod()' => [
                $types,
                $allTypes,
                MyClassWithUnionsAndIntersections::class,
                'MyMethod',
            ];
        }

        if (\PHP_VERSION_ID >= 80200) {
            $allTypes = [
                [],
                ['null'],
                ['int', 'null'],
                ['string'],
                ['Countable', 'ArrayAccess'],
                ['Salient\Tests\Reflection\MyBaseClass'],
                ['Salient\Tests\Reflection\MyClass', 'null'],
                ['Salient\Tests\Reflection\MyClass', 'null'],
                ['Salient\Tests\Reflection\MyClass', 'Countable', 'ArrayAccess'],
                ['Salient\Tests\Reflection\MyClass', 'null'],
                ['string'],
                ['Salient\Tests\Reflection\MyClass', 'string'],
                ['Salient\Tests\Reflection\MyClass', 'string', 'null'],
                ['Salient\Tests\Reflection\MyClass', 'array'],
                ['Salient\Tests\Reflection\MyClass', 'string', 'null'],
                ['Salient\Tests\Reflection\MyClass', 'Countable', 'ArrayAccess', 'string'],
                ['Salient\Tests\Reflection\MyClass', 'Countable', 'ArrayAccess', 'string', 'null'],
                ['Salient\Tests\Reflection\MyClass', 'Countable', 'ArrayAccess', 'array'],
                ['Salient\Tests\Reflection\MyClass', 'Countable', 'ArrayAccess', 'string', 'null'],
                ['string'],
            ];
            $types = $allTypes;
            $types[4] = [['Countable', 'ArrayAccess']];
            $types[8] = [['Salient\Tests\Reflection\MyClass', 'Countable'], ['Salient\Tests\Reflection\MyClass', 'ArrayAccess']];
            $types[15] = ['Salient\Tests\Reflection\MyClass', ['Countable', 'ArrayAccess'], 'string'];
            $types[16] = ['Salient\Tests\Reflection\MyClass', ['Countable', 'ArrayAccess'], 'string', 'null'];
            $types[17] = ['Salient\Tests\Reflection\MyClass', ['Countable', 'ArrayAccess'], 'array'];
            $types[18] = ['Salient\Tests\Reflection\MyClass', ['Countable', 'ArrayAccess'], 'string', 'null'];

            yield 'MyClassWithDnfTypes::MyMethod()' => [
                $types,
                $allTypes,
                MyClassWithDnfTypes::class,
                'MyMethod',
            ];
        }
    }

    /**
     * @dataProvider getConstantsProvider
     *
     * @param array<string,mixed> $expected
     * @param ReflectionClass<object>|class-string $class
     */
    public function testGetConstants(array $expected, $class): void
    {
        $this->assertSame($expected, Reflect::getConstants($class));
    }

    /**
     * @return array<array{array<string,mixed>,ReflectionClass<object>|class-string}>
     */
    public static function getConstantsProvider(): array
    {
        return [
            [
                [
                    'FOO' => 0,
                    'BAR' => 1,
                    'BAZ' => 2,
                ],
                MyEnum::class,
            ],
            [
                [
                    'FOO' => 0,
                    'BAR' => 1,
                    'BAZ' => 2,
                ],
                new ReflectionClass(MyEnum::class),
            ],
            [
                [
                    'FOO' => 'Foo',
                    'BAR' => 'Bar',
                    'BAZ' => 'Baz',
                    'QUX' => 'Baz',
                ],
                MyDict::class,
            ],
        ];
    }

    /**
     * @dataProvider getConstantsByValueProvider
     *
     * @param array<int|string,string[]|string> $expected
     * @param ReflectionClass<object>|class-string $class
     */
    public function testGetConstantsByValue(array $expected, $class): void
    {
        $this->assertSame($expected, Reflect::getConstantsByValue($class));
    }

    /**
     * @return array<array{array<int|string,string[]|string>,ReflectionClass<object>|class-string}>
     */
    public static function getConstantsByValueProvider(): array
    {
        return [
            [
                [
                    'FOO',
                    'BAR',
                    'BAZ',
                ],
                MyEnum::class,
            ],
            [
                [
                    'FOO',
                    'BAR',
                    'BAZ',
                ],
                new ReflectionClass(MyEnum::class),
            ],
            [
                [
                    'Foo' => 'FOO',
                    'Bar' => 'BAR',
                    'Baz' => ['BAZ', 'QUX'],
                ],
                MyDict::class,
            ],
        ];
    }

    public function testHasConstantWithValue(): void
    {
        $this->assertTrue(Reflect::hasConstantWithValue(MyEnum::class, 0));
        $this->assertFalse(Reflect::hasConstantWithValue(MyEnum::class, '0'));
        $this->assertFalse(Reflect::hasConstantWithValue(new ReflectionClass(MyEnum::class), 3));
        $this->assertTrue(Reflect::hasConstantWithValue(MyDict::class, 'Foo'));
        $this->assertTrue(Reflect::hasConstantWithValue(MyDict::class, 'Baz'));
        $this->assertFalse(Reflect::hasConstantWithValue(MyDict::class, 'Qux'));
        $this->assertFalse(Reflect::hasConstantWithValue(MyDict::class, 0));
    }

    /**
     * @dataProvider getConstantNameProvider
     *
     * @param array{class-string<Throwable>,string}|string $expected
     * @param ReflectionClass<object>|class-string $class
     * @param mixed $value
     */
    public function testGetConstantName($expected, $class, $value): void
    {
        if (is_array($expected)) {
            $this->expectException($expected[0]);
            $this->expectExceptionMessage($expected[1]);
            Reflect::getConstantName($class, $value);
            return;
        }
        $this->assertSame($expected, Reflect::getConstantName($class, $value));
    }

    /**
     * @return array<array{array{class-string<Throwable>,string}|string,ReflectionClass<object>|class-string,mixed}>
     */
    public static function getConstantNameProvider(): array
    {
        return [
            [
                'FOO',
                MyEnum::class,
                0,
            ],
            [
                'FOO',
                new ReflectionClass(MyEnum::class),
                0,
            ],
            [
                'BAR',
                MyDict::class,
                'Bar',
            ],
            [
                [
                    InvalidArgumentException::class,
                    'Value matches multiple constants: Baz',
                ],
                MyDict::class,
                'Baz',
            ],
            [
                [
                    InvalidArgumentException::class,
                    'Invalid value: 0',
                ],
                MyDict::class,
                0,
            ],
            [
                [
                    InvalidArgumentException::class,
                    'Invalid value: {"foo":"bar"}',
                ],
                MyDict::class,
                ['foo' => 'bar'],
            ],
        ];
    }

    /**
     * @dataProvider getConstantValueProvider
     *
     * @param array{class-string<Throwable>,string}|int|string $expected
     * @param ReflectionClass<object>|class-string $class
     */
    public function testGetConstantValue($expected, $class, string $name, bool $ignoreCase = false): void
    {
        if (is_array($expected)) {
            $this->expectException($expected[0]);
            $this->expectExceptionMessage($expected[1]);
            Reflect::getConstantValue($class, $name, $ignoreCase);
            return;
        }
        $this->assertSame($expected, Reflect::getConstantValue($class, $name, $ignoreCase));
    }

    /**
     * @return array<array{array{class-string<Throwable>,string}|int|string,ReflectionClass<object>|class-string,string,3?:bool}>
     */
    public static function getConstantValueProvider(): array
    {
        return [
            [
                0,
                MyEnum::class,
                'FOO',
            ],
            [
                0,
                new ReflectionClass(MyEnum::class),
                'FOO',
            ],
            [
                [
                    InvalidArgumentException::class,
                    'Invalid name: foo',
                ],
                MyEnum::class,
                'foo',
            ],
            [
                0,
                MyEnum::class,
                'foo',
                true,
            ],
            [
                [
                    InvalidArgumentException::class,
                    'Invalid name: QUX',
                ],
                MyEnum::class,
                'QUX',
                true,
            ],
            [
                'Bar',
                MyDict::class,
                'BAR',
            ],
        ];
    }

    public function testNamedType(): void
    {
        $types = Reflect::normaliseType(
            (new ReflectionParameter(
                [MyClass::class, '__construct'],
                'altId'
            ))->getType()
        );
        $this->assertIsArray($types);
        $this->assertCount(2, $types);

        $this->assertInstanceOf(NamedType::class, $type = $types[0]);
        $this->assertSame('int', $type->getName());
        $this->assertSame('int', (string) $type);
        $this->assertTrue($type->isBuiltin());
        $this->assertFalse($type->allowsNull());

        $this->assertInstanceOf(NamedType::class, $type = $types[1]);
        $this->assertSame('null', $type->getName());
        $this->assertSame('null', (string) $type);
        $this->assertTrue($type->isBuiltin());
        $this->assertTrue($type->allowsNull());
    }
}
