<?php declare(strict_types=1);

namespace Lkrms\Tests\Utility;

use Lkrms\Tests\Utility\Reflection\MyBaseClass;
use Lkrms\Tests\Utility\Reflection\MyBaseInterface;
use Lkrms\Tests\Utility\Reflection\MyBaseTrait;
use Lkrms\Tests\Utility\Reflection\MyClass;
use Lkrms\Tests\Utility\Reflection\MyClassWithUnionsAndIntersections;
use Lkrms\Tests\Utility\Reflection\MyInterface;
use Lkrms\Tests\Utility\Reflection\MyOtherClass;
use Lkrms\Tests\Utility\Reflection\MyOtherInterface;
use Lkrms\Tests\Utility\Reflection\MyReusedTrait;
use Lkrms\Tests\Utility\Reflection\MySubclass;
use Lkrms\Tests\Utility\Reflection\MyTrait;
use Lkrms\Utility\Reflect;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionProperty;

final class ReflectTest extends \Lkrms\Tests\TestCase
{
    /**
     * @dataProvider getClassesBetweenProvider
     *
     * @template TParent of object
     * @template TChild of TParent
     *
     * @param string[] $expected
     * @param class-string<TChild> $child
     * @param class-string<TParent> $parent
     */
    public function testGetClassesBetween(array $expected, string $child, string $parent, bool $withParent = true)
    {
        $this->assertSame($expected, Reflect::getClassesBetween($child, $parent, $withParent));
    }

    public static function getClassesBetweenProvider()
    {
        return [
            'interface to interface' => [
                [],
                MyInterface::class,
                MyBaseInterface::class,
            ],
            'trait to trait' => [
                [],
                MyTrait::class,
                MyBaseTrait::class,
            ],
            'class to interface' => [
                [],
                MyClass::class,
                MyInterface::class,
            ],
            'subclass to interface' => [
                [],
                MySubclass::class,
                MyInterface::class,
            ],
            'unrelated classes' => [
                [],
                MyOtherClass::class,
                MyClass::class,
            ],
            'parent to child' => [
                [],
                MyClass::class,
                MySubclass::class,
            ],
            'same class #1' => [
                [],
                MyClass::class,
                MyClass::class,
                false,
            ],
            'same class #2' => [
                [MyClass::class],
                MyClass::class,
                MyClass::class,
            ],
            'child to parent #1' => [
                [MySubclass::class],
                MySubclass::class,
                MyClass::class,
                false,
            ],
            'child to parent #2' => [
                [MySubclass::class, MyClass::class],
                MySubclass::class,
                MyClass::class,
            ],
            'child to grandparent #1' => [
                [MySubclass::class, MyClass::class],
                MySubclass::class,
                MyBaseClass::class,
                false,
            ],
            'child to grandparent #2' => [
                [MySubclass::class, MyClass::class, MyBaseClass::class],
                MySubclass::class,
                MyBaseClass::class,
            ],
        ];
    }

    public function testGetAllTypes()
    {
        $method = (new ReflectionClass(MyClass::class))->getConstructor();
        $types = [];
        foreach ($method->getParameters() as $param) {
            $types[] = array_map(
                function ($type): string {
                    /** @var ReflectionNamedType $type */
                    return $type->getName();
                },
                Reflect::getAllTypes($param->getType())
            );
        }
        $this->assertSame([
            [],
            ['int'],
            ['string'],
            ['Lkrms\Tests\Utility\Reflection\MyClass'],
            ['Lkrms\Tests\Utility\Reflection\MyClass'],
        ], $types);
    }

    public function testGetAllTypeNames()
    {
        $method = (new ReflectionClass(MyClass::class))->getConstructor();
        $names = [];
        foreach ($method->getParameters() as $param) {
            $names[] = Reflect::getAllTypeNames($param->getType());
        }
        $this->assertSame([
            [],
            ['int'],
            ['string'],
            ['Lkrms\Tests\Utility\Reflection\MyClass'],
            ['Lkrms\Tests\Utility\Reflection\MyClass'],
        ], $names);
    }

    /**
     * @dataProvider getAllClassDocCommentsProvider
     */
    public function testGetAllClassDocComments(ReflectionClass $class, array $expected)
    {
        $this->assertSame($expected, Reflect::getAllClassDocComments($class));
    }

    public static function getAllClassDocCommentsProvider()
    {
        return [
            MySubclass::class => [
                new ReflectionClass(MySubclass::class),
                [
                    MySubclass::class => "/**\n * MySubclass\n */",
                    MyClass::class => "/**\n * MyClass\n */",
                    MyBaseClass::class => "/**\n * MyBaseClass\n */",
                    MyInterface::class => "/**\n * MyInterface\n */",
                    MyBaseInterface::class => "/**\n * MyBaseInterface\n */",
                    MyOtherInterface::class => "/**\n * MyOtherInterface\n */",
                ],
            ],
        ];
    }

    /**
     * @dataProvider getAllMethodDocCommentsProvider
     */
    public function testGetAllMethodDocComments(
        ReflectionMethod $method,
        array $expected,
        ?array $expectedClassDocComments = null
    ) {
        if (is_null($expectedClassDocComments)) {
            $this->assertSame($expected, Reflect::getAllMethodDocComments($method));

            return;
        }

        $this->assertSame($expected, Reflect::getAllMethodDocComments($method, $classDocComments));
        $this->assertSame($expectedClassDocComments, $classDocComments);
    }

    public static function getAllMethodDocCommentsProvider()
    {
        $expected = [
            MySubclass::class => "/**\n     * MySubclass::MyDocumentedMethod() PHPDoc\n     */",
            MyClass::class => "/**\n     * MyClass::MyDocumentedMethod() PHPDoc\n     */",
            MyTrait::class => "/**\n     * MyTrait::MyDocumentedMethod() PHPDoc\n     */",
            MyBaseTrait::class => "/**\n     * MyBaseTrait::MyDocumentedMethod() PHPDoc\n     */",
            MyReusedTrait::class => "/**\n     * MyReusedTrait::MyDocumentedMethod() PHPDoc\n     */",
            MyBaseClass::class => "/**\n     * MyBaseClass::MyDocumentedMethod() PHPDoc\n     */",
            MyInterface::class => "/**\n     * MyInterface::MyDocumentedMethod() PHPDoc\n     */",
            MyBaseInterface::class => "/**\n     * MyBaseInterface::MyDocumentedMethod() PHPDoc\n     */",
            MyOtherInterface::class => "/**\n     * MyOtherInterface::MyDocumentedMethod() PHPDoc\n     */",
        ];

        return [
            MySubclass::class . '::MyDocumentedMethod()' => [
                (new ReflectionClass(MySubclass::class))->getMethod('MyDocumentedMethod'),
                $expected,
            ],
            MySubclass::class . '::MyDocumentedMethod() + classDocComments' => [
                (new ReflectionClass(MySubclass::class))->getMethod('MyDocumentedMethod'),
                $expected,
                [
                    MySubclass::class => "/**\n * MySubclass\n */",
                    MyClass::class => "/**\n * MyClass\n */",
                    MyTrait::class => "/**\n * MyTrait\n */",
                    MyBaseTrait::class => "/**\n * MyBaseTrait\n */",
                    MyReusedTrait::class => "/**\n * MyReusedTrait\n */",
                    MyBaseClass::class => "/**\n * MyBaseClass\n */",
                    MyInterface::class => "/**\n * MyInterface\n */",
                    MyBaseInterface::class => "/**\n * MyBaseInterface\n */",
                    MyOtherInterface::class => "/**\n * MyOtherInterface\n */",
                ],
            ],
        ];
    }

    /**
     * @dataProvider getAllPropertyDocCommentsProvider
     */
    public function testGetAllPropertyDocComments(
        ReflectionProperty $property,
        array $expected,
        ?array $expectedClassDocComments = null
    ) {
        if (is_null($expectedClassDocComments)) {
            $this->assertSame($expected, Reflect::getAllPropertyDocComments($property));

            return;
        }

        $this->assertSame($expected, Reflect::getAllPropertyDocComments($property, $classDocComments));
        $this->assertSame($expectedClassDocComments, $classDocComments);
    }

    public static function getAllPropertyDocCommentsProvider()
    {
        $expected = [
            MySubclass::class => "/**\n     * MySubclass::\$MyDocumentedProperty PHPDoc\n     */",
            MyClass::class => "/**\n     * MyClass::\$MyDocumentedProperty PHPDoc\n     */",
            MyTrait::class => "/**\n     * MyTrait::\$MyDocumentedProperty PHPDoc\n     */",
            MyBaseTrait::class => "/**\n     * MyBaseTrait::\$MyDocumentedProperty PHPDoc\n     */",
            MyReusedTrait::class => "/**\n     * MyReusedTrait::\$MyDocumentedProperty PHPDoc\n     */",
            MyBaseClass::class => "/**\n     * MyBaseClass::\$MyDocumentedProperty PHPDoc\n     */",
        ];

        return [
            MySubclass::class . '::$MyDocumentedProperty' => [
                (new ReflectionClass(MySubclass::class))->getProperty('MyDocumentedProperty'),
                $expected,
            ],
            MySubclass::class . '::$MyDocumentedProperty + classDocComments' => [
                (new ReflectionClass(MySubclass::class))->getProperty('MyDocumentedProperty'),
                $expected,
                [
                    MySubclass::class => "/**\n * MySubclass\n */",
                    MyClass::class => "/**\n * MyClass\n */",
                    MyTrait::class => "/**\n * MyTrait\n */",
                    MyBaseTrait::class => "/**\n * MyBaseTrait\n */",
                    MyReusedTrait::class => "/**\n * MyReusedTrait\n */",
                    MyBaseClass::class => "/**\n * MyBaseClass\n */",
                ],
            ],
        ];
    }

    /**
     * @requires PHP >= 8.1
     */
    public function testGetTypeDeclaration()
    {
        $method = (new ReflectionClass(MyClassWithUnionsAndIntersections::class))->getMethod('MyMethod');
        $types = [];
        foreach ($method->getParameters() as $param) {
            $types[] = Reflect::getTypeDeclaration(
                $param->getType(),
                '\\',
                fn($name) => $name == MyClass::class ? 'MyClass' : null,
            );
        }
        $this->assertSame([
            '',
            '?int',
            'string',
            '\Countable&\ArrayAccess',
            '\Lkrms\Tests\Utility\Reflection\MyBaseClass',
            '?MyClass',
            '?MyClass',
            '?MyClass',
            'string',
            'MyClass|string',
            'MyClass|string|null',
            'MyClass|array',
            'MyClass|string|null',
            'string',
        ], $types);
    }

    /**
     * @requires PHP >= 8.1
     */
    public function testGetParameterDeclaration()
    {
        $method = (new ReflectionClass(MyClassWithUnionsAndIntersections::class))->getMethod('MyMethod');
        $params = [];
        foreach ($method->getParameters() as $param) {
            $params[] = Reflect::getParameterDeclaration(
                $param,
                '',
                fn($name) => $name == MyClass::class ? 'MyClass' : null,
            );
        }
        $this->assertSame([
            '$mixed',
            '?int $nullableInt',
            'string $string',
            'Countable&ArrayAccess $intersection',
            'Lkrms\Tests\Utility\Reflection\MyBaseClass $class',
            '?MyClass $nullableClass',
            '?MyClass &$nullableClassByRef',
            '?MyClass $nullableAndOptionalClass = null',
            'string $optionalString = MyClass::MY_CONSTANT',
            'MyClass|string $union = SELF::MY_CONSTANT',
            "MyClass|string|null \$nullableUnion = 'literal'",
            "MyClass|array \$optionalArrayUnion = ['key'=>'value']",
            'MyClass|string|null &$nullableUnionByRef = null',
            'string &...$variadicByRef',
        ], $params);
    }
}
