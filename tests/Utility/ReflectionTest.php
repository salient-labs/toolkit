<?php

declare(strict_types=1);

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
use ReflectionNamedType;
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
        foreach ($method->getParameters() as $param)
        {
            $types[] = array_map(
                fn(ReflectionNamedType $type) => $type->getName(),
                (new Reflection())->getAllTypes($param->getType())
            );
        }
        $this->assertSame([
            [],
                ["int"],
                ["string"],
                ["Lkrms\\Tests\\Utility\\Reflection\\MyClass"],
                ["Lkrms\\Tests\\Utility\\Reflection\\MyClass"],
        ], $types);
    }

    public function testGetAllTypeNames()
    {
        $method = (new ReflectionClass(MyClass::class))->getConstructor();
        $names  = [];
        foreach ($method->getParameters() as $param)
        {
            $names[] = Reflect::getAllTypeNames($param->getType());
        }
        $this->assertSame([
            [],
                ["int"],
                ["string"],
                ["Lkrms\\Tests\\Utility\\Reflection\\MyClass"],
                ["Lkrms\\Tests\\Utility\\Reflection\\MyClass"],
        ], $names);
    }

    public function testGetAllMethodDocComments()
    {
        $method      = (new ReflectionClass(MySubclass::class))->getMethod("MyDocumentedMethod");
        $docComments = Reflect::getAllMethodDocComments($method);
        $this->assertSame([
            "/**
     * MySubclass::MyDocumentedMethod() PHPDoc
     */",
            "/**
     * MyClass::MyDocumentedMethod() PHPDoc
     */",
            "/**
     * MyTrait::MyDocumentedMethod() PHPDoc
     */",
            "/**
     * MyBaseTrait::MyDocumentedMethod() PHPDoc
     */",
            "/**
     * MyBaseClass::MyDocumentedMethod() PHPDoc
     */",
            "/**
     * MyInterface::MyDocumentedMethod() PHPDoc
     */",
            "/**
     * MyBaseInterface::MyDocumentedMethod() PHPDoc
     */",
            "/**
     * MyOtherInterface::MyDocumentedMethod() PHPDoc
     */",
        ], $docComments);
    }

    public function testGetAllPropertyDocComments()
    {
        $property    = (new ReflectionClass(MySubclass::class))->getProperty("MyDocumentedProperty");
        $docComments = Reflect::getAllPropertyDocComments($property);
        $this->assertSame([
            "/**
     * MySubclass::\$MyDocumentedProperty PHPDoc
     */",
            "/**
     * MyClass::\$MyDocumentedProperty PHPDoc
     */",
            "/**
     * MyTrait::\$MyDocumentedProperty PHPDoc
     */",
            "/**
     * MyBaseTrait::\$MyDocumentedProperty PHPDoc
     */",
            "/**
     * MyBaseClass::\$MyDocumentedProperty PHPDoc
     */",
        ], $docComments);
    }

    public function testGetTypeDeclaration()
    {
        $method = (new ReflectionClass(MyClass::class))->getMethod("MyMethod");
        $types  = [];
        foreach ($method->getParameters() as $param)
        {
            $types[] = Reflect::getTypeDeclaration(
                $param->getType(),
                "\\",
                fn($name) => $name == MyClass::class ? "MyClass" : null,
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
        $method = (new ReflectionClass(MyClass::class))->getMethod("MyMethod");
        $params = [];
        foreach ($method->getParameters() as $param)
        {
            $params[] = Reflect::getParameterDeclaration(
                $param,
                "",
                fn($name) => $name == MyClass::class ? "MyClass" : null,
            );
        }
        $this->assertSame([
            "mixed \$mixed",
            "?int \$nullableInt",
            "string \$string",
            "Countable&ArrayAccess \$intersection",
            "Lkrms\Tests\Utility\Reflection\MyBaseClass \$class",
            "?MyClass \$nullableClass",
            "?MyClass &\$nullableClassByRef",
            "?MyClass \$nullableAndOptionalClass = null",
            "string \$optionalString = MyClass::MY_CONSTANT",
            "MyClass|string \$union = SELF::MY_CONSTANT",
            "MyClass|string|null \$nullableUnion = 'literal'",
            "MyClass|array \$optionalArrayUnion = ['key'=>'value']",
            "MyClass|string|null &\$nullableUnionByRef = null",
            "string &...\$variadicByRef",
        ], $params);
    }

}
