<?php declare(strict_types=1);

namespace Lkrms\Tests\Utility;

use Lkrms\Facade\Reflect;
use Lkrms\Tests\Utility\Reflection\MyBaseClass;
use Lkrms\Tests\Utility\Reflection\MyBaseInterface;
use Lkrms\Tests\Utility\Reflection\MyClass;
use Lkrms\Tests\Utility\Reflection\MyInterface;
use Lkrms\Tests\Utility\Reflection\MyOtherClass;
use Lkrms\Tests\Utility\Reflection\MySubclass;
use Lkrms\Utility\Reflection;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionProperty;
use UnexpectedValueException;

final class ReflectionTest extends \Lkrms\Tests\TestCase
{
    public function testGetClassNamesBetweenInterfaces()
    {
        $this->expectException(UnexpectedValueException::class);
        Reflect::getClassNamesBetween(MyInterface::class, MyBaseInterface::class);
    }

    public function testGetClassNamesBetweenClassAndInterface()
    {
        $this->expectException(UnexpectedValueException::class);
        Reflect::getClassNamesBetween(MyClass::class, MyInterface::class);
    }

    public function testGetClassNamesBetweenSubclassAndInterface()
    {
        $this->expectException(UnexpectedValueException::class);
        Reflect::getClassNamesBetween(MySubclass::class, MyInterface::class);
    }

    public function testGetClassNamesBetweenUnrelatedClasses()
    {
        $this->expectException(UnexpectedValueException::class);
        Reflect::getClassNamesBetween(MyOtherClass::class, MyClass::class);
    }

    public function testGetClassNamesBetweenParentAndChild()
    {
        $this->expectException(UnexpectedValueException::class);
        Reflect::getClassNamesBetween(MyClass::class, MySubclass::class);
    }

    public function testGetClassNamesBetweenSameClass()
    {
        $this->assertEquals(
            [],
            Reflect::getClassNamesBetween(MyClass::class, MyClass::class, false)
        );
        $this->assertEquals(
            [MyClass::class],
            Reflect::getClassNamesBetween(MyClass::class, MyClass::class)
        );
    }

    public function testGetClassNamesBetweenChildAndParent()
    {
        $this->assertEquals(
            [MySubclass::class],
            Reflect::getClassNamesBetween(MySubclass::class, MyClass::class, false)
        );
        $this->assertEquals(
            [MySubclass::class, MyClass::class],
            Reflect::getClassNamesBetween(MySubclass::class, MyClass::class)
        );
    }

    public function testGetClassNamesBetweenChildAndGrandparent()
    {
        $this->assertEquals(
            [MySubclass::class, MyClass::class],
            Reflect::getClassNamesBetween(MySubclass::class, MyBaseClass::class, false)
        );
        $this->assertEquals(
            [MySubclass::class, MyClass::class, MyBaseClass::class],
            Reflect::getClassNamesBetween(MySubclass::class, MyBaseClass::class)
        );
    }

    public function testGetAllTypes()
    {
        $method = (new ReflectionClass(MyClass::class))->getConstructor();
        $types  = [];
        foreach ($method->getParameters() as $param) {
            $types[] = array_map(
                function ($type): string {
                    /** @var ReflectionNamedType $type */
                    return $type->getName();
                },
                (new Reflection())->getAllTypes($param->getType())
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
        $names  = [];
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

    public function getAllClassDocCommentsProvider()
    {
        return [
            MySubclass::class => [
                new ReflectionClass(MySubclass::class),
                [
                    "/**\n * MySubclass\n */",
                    "/**\n * MyClass\n */",
                    "/**\n * MyBaseClass\n */",
                    "/**\n * MyInterface\n */",
                    "/**\n * MyBaseInterface\n */",
                    "/**\n * MyOtherInterface\n */",
                ],
            ],
        ];
    }

    /**
     * @dataProvider getAllMethodDocCommentsProvider
     */
    public function testGetAllMethodDocComments(ReflectionMethod $method, array $expected, ?array $expectedClassDocComments = null)
    {
        if (is_null($expectedClassDocComments)) {
            $this->assertSame($expected, Reflect::getAllMethodDocComments($method));

            return;
        }

        $this->assertSame($expected, Reflect::getAllMethodDocComments($method, $classDocComments));
        $this->assertSame($expectedClassDocComments, $classDocComments);
    }

    public function getAllMethodDocCommentsProvider()
    {
        $expected = [
            "/**\n     * MySubclass::MyDocumentedMethod() PHPDoc\n     */",
            "/**\n     * MyClass::MyDocumentedMethod() PHPDoc\n     */",
            "/**\n     * MyTrait::MyDocumentedMethod() PHPDoc\n     */",
            "/**\n     * MyBaseTrait::MyDocumentedMethod() PHPDoc\n     */",
            "/**\n     * MyReusedTrait::MyDocumentedMethod() PHPDoc\n     */",
            "/**\n     * MyBaseClass::MyDocumentedMethod() PHPDoc\n     */",
            "/**\n     * MyInterface::MyDocumentedMethod() PHPDoc\n     */",
            "/**\n     * MyBaseInterface::MyDocumentedMethod() PHPDoc\n     */",
            "/**\n     * MyOtherInterface::MyDocumentedMethod() PHPDoc\n     */",
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
                    "/**\n * MySubclass\n */",
                    "/**\n * MyClass\n */",
                    "/**\n * MyTrait\n */",
                    "/**\n * MyBaseTrait\n */",
                    "/**\n * MyReusedTrait\n */",
                    "/**\n * MyBaseClass\n */",
                    "/**\n * MyInterface\n */",
                    "/**\n * MyBaseInterface\n */",
                    "/**\n * MyOtherInterface\n */",
                ],
            ],
        ];
    }

    /**
     * @dataProvider getAllPropertyDocCommentsProvider
     */
    public function testGetAllPropertyDocComments(ReflectionProperty $property, array $expected, ?array $expectedClassDocComments = null)
    {
        if (is_null($expectedClassDocComments)) {
            $this->assertSame($expected, Reflect::getAllPropertyDocComments($property));

            return;
        }

        $this->assertSame($expected, Reflect::getAllPropertyDocComments($property, $classDocComments));
        $this->assertSame($expectedClassDocComments, $classDocComments);
    }

    public function getAllPropertyDocCommentsProvider()
    {
        $expected = [
            "/**\n     * MySubclass::\$MyDocumentedProperty PHPDoc\n     */",
            "/**\n     * MyClass::\$MyDocumentedProperty PHPDoc\n     */",
            "/**\n     * MyTrait::\$MyDocumentedProperty PHPDoc\n     */",
            "/**\n     * MyBaseTrait::\$MyDocumentedProperty PHPDoc\n     */",
            "/**\n     * MyReusedTrait::\$MyDocumentedProperty PHPDoc\n     */",
            "/**\n     * MyBaseClass::\$MyDocumentedProperty PHPDoc\n     */",
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
                    "/**\n * MySubclass\n */",
                    "/**\n * MyClass\n */",
                    "/**\n * MyTrait\n */",
                    "/**\n * MyBaseTrait\n */",
                    "/**\n * MyReusedTrait\n */",
                    "/**\n * MyBaseClass\n */",
                ],
            ],
        ];
    }

    public function testGetTypeDeclaration()
    {
        $method = (new ReflectionClass(MyClass::class))->getMethod('MyMethod');
        $types  = [];
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

    public function testGetParameterDeclaration()
    {
        $method = (new ReflectionClass(MyClass::class))->getMethod('MyMethod');
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
