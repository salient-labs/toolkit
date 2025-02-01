<?php declare(strict_types=1);

namespace Salient\Tests\Core\Reflection;

use Salient\Contract\Core\Entity\Relatable;
use Salient\Core\Reflection\ClassReflection;
use Salient\Core\Reflection\MethodReflection;
use Salient\Core\Reflection\ParameterIndex;
use Salient\Core\Reflection\PropertyRelationship;
use Salient\Tests\Core\Introspector\A;
use Salient\Tests\Core\Introspector\B;
use Salient\Tests\Core\Introspector\C;
use Salient\Tests\Core\Introspector\D;
use Salient\Tests\Core\Introspector\X;
use Salient\Tests\Core\Introspector\Y;
use Salient\Tests\Core\Introspector\Z;
use Salient\Tests\TestCase;
use DateTimeImmutable;
use ReflectionProperty;

/**
 * @covers \Salient\Core\Reflection\ClassReflection
 * @covers \Salient\Core\Reflection\MethodReflection
 * @covers \Salient\Core\Reflection\ParameterIndex
 * @covers \Salient\Core\Reflection\PropertyRelationship
 */
final class ClassReflectionTest extends TestCase
{
    /**
     * @dataProvider getConstructorProvider
     *
     * @template T of object
     *
     * @param T|class-string<T> $objectOrClass
     */
    public function testGetConstructor(?ParameterIndex $expected, $objectOrClass): void
    {
        $class = new ClassReflection($objectOrClass);
        $method = $class->getConstructor();
        if ($expected) {
            $this->assertNotNull($method);
            $this->assertEquals(new MethodReflection($objectOrClass, $method->name), $method);
            /** @disregard P1013 */
            $this->assertEquals($expected, $method->getParameterIndex());
            $this->assertEquals(
                $expected->RequiredArgumentCount,
                $method->getNumberOfRequiredParameters(),
            );
        } else {
            $this->assertNull($method);
        }
    }

    /**
     * @return array<array{ParameterIndex|null,object|class-string}>
     */
    public static function getConstructorProvider(): array
    {
        $indexB = new ParameterIndex(
            ['created_at' => 'createdAt'],
            ['createdAt' => 0],
            [null],
            ['created_at' => 'createdAt'],
            ['created_at' => 'createdAt'],
            [],
            ['created_at' => 'createdAt'],
            [],
            ['created_at' => 'DateTimeInterface'],
            1,
        );

        return [
            [
                null,
                A::class,
            ],
            [
                $indexB,
                B::class,
            ],
            [
                $indexB,
                new B(new DateTimeImmutable()),
            ],
            [
                new ParameterIndex(
                    [
                        'long' => 'long',
                        'short' => 'short',
                        'valueName' => 'valueName',
                        'type' => 'type',
                        'valueType' => 'valueType',
                        'description' => 'description',
                    ],
                    [
                        'long' => 0,
                        'short' => 1,
                        'valueName' => 2,
                        'type' => 3,
                        'valueType' => 4,
                        'description' => 5,
                    ],
                    [null, null, null, 1, 0, null],
                    [
                        'long' => 'long',
                        'type' => 'type',
                        'valueType' => 'valueType',
                    ],
                    [
                        'long' => 'long',
                    ],
                    [
                        'description' => 'description',
                    ],
                    [],
                    [
                        'long' => 'string',
                        'short' => 'string',
                        'valueName' => 'string',
                        'type' => 'int',
                        'valueType' => 'int',
                        'description' => 'string',
                    ],
                    [],
                    3,
                ),
                C::class,
            ],
        ];
    }

    /**
     * @dataProvider implementsInterfaceProvider
     *
     * @template T of object
     *
     * @param T|class-string<T> $objectOrClass
     */
    public function testImplementsInterface(
        $objectOrClass,
        bool $isHierarchical,
        bool $isReadable,
        bool $isWritable,
        bool $isExtensible,
        bool $isNormalisable,
        bool $isProvidable,
        bool $isRelatable,
        bool $isTreeable,
        bool $isTemporal
    ): void {
        $class = new ClassReflection($objectOrClass);
        $this->assertSame($isHierarchical, $class->isHierarchical());
        $this->assertSame($isReadable, $class->isReadable());
        $this->assertSame($isWritable, $class->isWritable());
        $this->assertSame($isExtensible, $class->isExtensible());
        $this->assertSame($isNormalisable, $class->isNormalisable());
        $this->assertSame($isProvidable, $class->isProvidable());
        $this->assertSame($isRelatable, $class->isRelatable());
        $this->assertSame($isTreeable, $class->isTreeable());
        $this->assertSame($isTemporal, $class->isTemporal());
    }

    /**
     * @return array<array{class-string,bool,bool,bool,bool,bool,bool,bool,bool,bool}>
     */
    public static function implementsInterfaceProvider(): array
    {
        return [
            [A::class, false, true, true, true, true, false, false, false, false],
            [B::class, false, true, true, true, true, false, false, false, false],
            [C::class, false, false, false, false, false, false, false, false, true],
        ];
    }

    /**
     * @dataProvider getPropertyNamesProvider
     *
     * @template T of object
     *
     * @param T|class-string<T> $objectOrClass
     */
    public function testGetPropertyNames(
        $objectOrClass,
        ?string $dynamicProperties,
        ?string $dynamicPropertyNames,
        ?string $parent,
        ?string $children
    ): void {
        $class = new ClassReflection($objectOrClass);
        $this->assertSame($dynamicProperties, $class->getDynamicPropertiesProperty());
        $this->assertSame($dynamicPropertyNames, $class->getDynamicPropertyNamesProperty());
        $this->assertSame($parent, $class->getParentProperty());
        $this->assertSame($children, $class->getChildrenProperty());
    }

    /**
     * @return array<array{class-string,string|null,string|null,string|null,string|null}>
     */
    public static function getPropertyNamesProvider(): array
    {
        return [
            [A::class, 'MetaProperties', 'MetaPropertyNames', null, null],
            [B::class, 'MetaProperties', 'MetaPropertyNames', null, null],
            [C::class, null, null, null, null],
        ];
    }

    /**
     * @dataProvider getDeclaredNamesProvider
     *
     * @template T of object
     *
     * @param list<string> $expected
     * @param T|class-string<T> $objectOrClass
     */
    public function testGetDeclaredNames(array $expected, $objectOrClass): void
    {
        $class = new ClassReflection($objectOrClass);
        $this->assertEqualsCanonicalizing($expected, $class->getDeclaredNames());
    }

    /**
     * @return array<array{list<string>,class-string}>
     */
    public static function getDeclaredNamesProvider(): array
    {
        return [
            [
                ['id', 'name', 'not_writable'],
                A::class,
            ],
            [
                ['id', 'name', 'not_writable', 'created_at', 'modified_at', 'data', 'meta', 'secret'],
                B::class,
            ],
            [
                ['Long', 'Short'],
                C::class,
            ],
            [
                ['Long', 'Short', 'Once', 'Then', 'Always', 'Now', 'Uuid'],
                D::class,
            ],
            [
                ['my-int', 'my-y'],
                X::class,
            ],
            [
                ['MyX'],
                Y::class,
            ],
        ];
    }

    /**
     * @dataProvider getSerializableNamesProvider
     *
     * @template T of object
     *
     * @param list<string> $expected
     * @param T|class-string<T> $objectOrClass
     */
    public function testGetSerializableNames(array $expected, $objectOrClass): void
    {
        $class = new ClassReflection($objectOrClass);
        $this->assertEqualsCanonicalizing($expected, $class->getSerializableNames());
    }

    /**
     * @return array<array{list<string>,class-string}>
     */
    public static function getSerializableNamesProvider(): array
    {
        return [
            [
                ['id', 'name'],
                A::class,
            ],
            [
                ['id', 'name', 'data', 'meta'],
                B::class,
            ],
            [
                ['Long', 'Short'],
                C::class,
            ],
            [
                ['Long', 'Short', 'Once', 'Then', 'Always'],
                D::class,
            ],
            [
                ['my-y'],
                X::class,
            ],
            [
                ['MyX'],
                Y::class,
            ],
        ];
    }

    /**
     * @dataProvider getWritableNamesProvider
     *
     * @template T of object
     *
     * @param list<string> $expected
     * @param T|class-string<T> $objectOrClass
     */
    public function testGetWritableNames(array $expected, $objectOrClass): void
    {
        $class = new ClassReflection($objectOrClass);
        $this->assertEqualsCanonicalizing($expected, $class->getWritableNames());
    }

    /**
     * @return array<array{list<string>,class-string}>
     */
    public static function getWritableNamesProvider(): array
    {
        return [
            [
                ['id', 'name'],
                A::class,
            ],
            [
                ['id', 'name', 'data', 'meta', 'secret'],
                B::class,
            ],
            [
                ['Long', 'Short'],
                C::class,
            ],
            [
                ['Long', 'Short', 'Once', 'Then', 'Always'],
                D::class,
            ],
            [
                ['my-y'],
                X::class,
            ],
            [
                ['MyX'],
                Y::class,
            ],
        ];
    }

    /**
     * @dataProvider getReadablePropertyNamesProvider
     *
     * @template T of object
     *
     * @param array<string,string> $expected
     * @param T|class-string<T> $objectOrClass
     */
    public function testGetAccessiblePropertyNames(array $expected, $objectOrClass): void
    {
        $class = new ClassReflection($objectOrClass);
        $this->assertEquals($expected, $class->getAccessiblePropertyNames());
    }

    /**
     * @dataProvider getReadablePropertyNamesProvider
     *
     * @template T of object
     *
     * @param array<string,string> $expected
     * @param T|class-string<T> $objectOrClass
     */
    public function testGetReadablePropertyNames(array $expected, $objectOrClass): void
    {
        $class = new ClassReflection($objectOrClass);
        $this->assertEquals($expected, $class->getReadablePropertyNames());
    }

    /**
     * @return array<array{array<string,string>,class-string}>
     */
    public static function getReadablePropertyNamesProvider(): array
    {
        return [
            [
                [
                    'id' => 'Id',
                    'name' => 'Name',
                    'not_writable' => 'NotWritable',
                ],
                A::class,
            ],
            [
                [
                    'id' => 'Id',
                    'name' => 'Name',
                    'not_writable' => 'NotWritable',
                    'created_at' => 'CreatedAt',
                    'modified_at' => 'ModifiedAt',
                ],
                B::class,
            ],
            [
                [
                    'Long' => 'Long',
                    'Short' => 'Short',
                ],
                C::class,
            ],
            [
                [
                    'Long' => 'Long',
                    'Short' => 'Short',
                    'Once' => 'Once',
                    'Then' => 'Then',
                    'Always' => 'Always',
                ],
                D::class,
            ],
        ];
    }

    /**
     * @dataProvider getWritablePropertyNamesProvider
     *
     * @template T of object
     *
     * @param array<string,string> $expected
     * @param T|class-string<T> $objectOrClass
     */
    public function testGetWritablePropertyNames(array $expected, $objectOrClass): void
    {
        $class = new ClassReflection($objectOrClass);
        $this->assertEquals($expected, $class->getWritablePropertyNames());
    }

    /**
     * @return array<array{array<string,string>,class-string}>
     */
    public static function getWritablePropertyNamesProvider(): array
    {
        return [
            [
                [
                    'id' => 'Id',
                    'name' => 'Name',
                ],
                A::class,
            ],
            [
                [
                    'id' => 'Id',
                    'name' => 'Name',
                ],
                B::class,
            ],
            [
                [
                    'Long' => 'Long',
                    'Short' => 'Short',
                ],
                C::class,
            ],
            [
                [
                    'Long' => 'Long',
                    'Short' => 'Short',
                    'Once' => 'Once',
                    'Then' => 'Then',
                    'Always' => 'Always',
                ],
                D::class,
            ],
        ];
    }

    /**
     * @dataProvider getReadablePropertiesProvider
     *
     * @template T of object
     *
     * @param array<string,ReflectionProperty> $expected
     * @param T|class-string<T> $objectOrClass
     */
    public function testGetReadableProperties(array $expected, $objectOrClass): void
    {
        $class = new ClassReflection($objectOrClass);
        $this->assertEquals($expected, $class->getReadableProperties());
    }

    /**
     * @return array<array{array<string,ReflectionProperty>,class-string}>
     */
    public static function getReadablePropertiesProvider(): array
    {
        return [
            [
                [
                    'id' => new ReflectionProperty(A::class, 'Id'),
                    'name' => new ReflectionProperty(A::class, 'Name'),
                    'not_writable' => new ReflectionProperty(A::class, 'NotWritable'),
                ],
                A::class,
            ],
        ];
    }

    /**
     * @dataProvider getWritablePropertiesProvider
     *
     * @template T of object
     *
     * @param array<string,ReflectionProperty> $expected
     * @param T|class-string<T> $objectOrClass
     */
    public function testGetWritableProperties(array $expected, $objectOrClass): void
    {
        $class = new ClassReflection($objectOrClass);
        $this->assertEquals($expected, $class->getWritableProperties());
    }

    /**
     * @return array<array{array<string,ReflectionProperty>,class-string}>
     */
    public static function getWritablePropertiesProvider(): array
    {
        return [
            [
                [
                    'id' => new ReflectionProperty(A::class, 'Id'),
                    'name' => new ReflectionProperty(A::class, 'Name'),
                ],
                A::class,
            ],
        ];
    }

    /**
     * @dataProvider getPropertyActionsProvider
     *
     * @template T of object
     *
     * @param array<"get"|"isset"|"set"|"unset",array<string,MethodReflection>> $expected
     * @param T|class-string<T> $objectOrClass
     */
    public function testGetPropertyActions(array $expected, $objectOrClass): void
    {
        $class = new ClassReflection($objectOrClass);
        $this->assertEquals($expected, $class->getPropertyActions());
    }

    /**
     * @return array<array{array<"get"|"isset"|"set"|"unset",array<string,MethodReflection>>,class-string}>
     */
    public static function getPropertyActionsProvider(): array
    {
        return [
            [
                [],
                A::class,
            ],
            [
                [
                    'get' => [
                        'data' => new MethodReflection(B::class, '_getData'),
                        'meta' => new MethodReflection(B::class, '_getMeta'),
                    ],
                    'set' => [
                        'data' => new MethodReflection(B::class, '_setData'),
                        'meta' => new MethodReflection(B::class, '_setMeta'),
                        'secret' => new MethodReflection(B::class, '_setSecret'),
                    ],
                    'isset' => [
                        'meta' => new MethodReflection(B::class, '_issetMeta'),
                    ],
                    'unset' => [
                        'meta' => new MethodReflection(B::class, '_unsetMeta'),
                    ],
                ],
                B::class,
            ],
            [
                [],
                C::class,
            ],
            [
                [
                    'get' => [
                        'Uuid' => new MethodReflection(D::class, '_getUuid'),
                        'Now' => new MethodReflection(D::class, '_getNow'),
                    ],
                ],
                D::class,
            ],
        ];
    }

    /**
     * @dataProvider getActionPropertiesProvider
     *
     * @template T of object
     *
     * @param array<string,array<"get"|"isset"|"set"|"unset",MethodReflection>> $expected
     * @param T|class-string<T> $objectOrClass
     * @param "get"|"isset"|"set"|"unset" ...$action
     */
    public function testGetActionProperties(array $expected, $objectOrClass, string ...$action): void
    {
        $class = new ClassReflection($objectOrClass);
        $this->assertEquals($expected, $class->getActionProperties(...$action));
    }

    /**
     * @return array<array{array<string,array<"get"|"isset"|"set"|"unset",MethodReflection>>,class-string,...<"get"|"isset"|"set"|"unset">}>
     */
    public static function getActionPropertiesProvider(): array
    {
        return [
            [
                [],
                A::class,
            ],
            [
                [
                    'data' => [
                        'get' => new MethodReflection(B::class, '_getData'),
                        'set' => new MethodReflection(B::class, '_setData'),
                    ],
                    'meta' => [
                        'get' => new MethodReflection(B::class, '_getMeta'),
                        'set' => new MethodReflection(B::class, '_setMeta'),
                        'isset' => new MethodReflection(B::class, '_issetMeta'),
                        'unset' => new MethodReflection(B::class, '_unsetMeta'),
                    ],
                    'secret' => [
                        'set' => new MethodReflection(B::class, '_setSecret'),
                    ],
                ],
                B::class,
            ],
            [
                [
                    'data' => [
                        'get' => new MethodReflection(B::class, '_getData'),
                    ],
                    'meta' => [
                        'get' => new MethodReflection(B::class, '_getMeta'),
                    ],
                ],
                B::class,
                'get',
            ],
            [
                [
                    'meta' => [
                        'isset' => new MethodReflection(B::class, '_issetMeta'),
                    ],
                ],
                B::class,
                'isset',
            ],
            [
                [],
                C::class,
            ],
            [
                [
                    'Uuid' => [
                        'get' => new MethodReflection(D::class, '_getUuid'),
                    ],
                    'Now' => [
                        'get' => new MethodReflection(D::class, '_getNow'),
                    ],
                ],
                D::class,
            ],
        ];
    }

    /**
     * @dataProvider getDateNamesProvider
     *
     * @template T of object
     *
     * @param list<string> $expected
     * @param T|class-string<T> $objectOrClass
     */
    public function testGetDateNames(array $expected, $objectOrClass): void
    {
        $class = new ClassReflection($objectOrClass);
        $this->assertEquals($expected, $class->getDateNames());
    }

    /**
     * @return array<array{list<string>,class-string}>
     */
    public static function getDateNamesProvider(): array
    {
        return [
            [
                [],
                A::class,
            ],
            [
                ['created_at', 'modified_at'],
                B::class,
            ],
            [
                ['Long', 'Short'],
                C::class,
            ],
            [
                ['Once', 'Then', 'Now'],
                D::class,
            ],
        ];
    }

    /**
     * @dataProvider getPropertyRelationshipsProvider
     *
     * @template T of object
     *
     * @param array<string,PropertyRelationship> $expected
     * @param T|class-string<T> $objectOrClass
     */
    public function testGetPropertyRelationships(array $expected, $objectOrClass): void
    {
        $class = new ClassReflection($objectOrClass);
        $this->assertEquals($expected, $class->getPropertyRelationships());
    }

    /**
     * @return array<array{array<string,PropertyRelationship>,class-string}>
     */
    public static function getPropertyRelationshipsProvider(): array
    {
        return [
            [
                [],
                B::class,
            ],
            [
                [],
                D::class,
            ],
            [
                [
                    'my-y' => new PropertyRelationship('MyY', Relatable::ONE_TO_ONE, Y::class),
                ],
                X::class,
            ],
            [
                [
                    'MyX' => new PropertyRelationship('MyX', Relatable::ONE_TO_MANY, X::class),
                ],
                Y::class,
            ],
            [
                [
                    'Parent' => new PropertyRelationship('Parent', Relatable::ONE_TO_ONE, Z::class),
                    'Children' => new PropertyRelationship('Children', Relatable::ONE_TO_MANY, Z::class),
                ],
                Z::class,
            ],
        ];
    }
}
