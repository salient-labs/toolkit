<?php declare(strict_types=1);

namespace Salient\Tests\PHPDoc;

use Salient\PHPDoc\PHPDocUtil;
use Salient\Tests\Reflection\MyBaseClass;
use Salient\Tests\Reflection\MyBaseInterface;
use Salient\Tests\Reflection\MyBaseTrait;
use Salient\Tests\Reflection\MyClass;
use Salient\Tests\Reflection\MyClassWithDnfTypes;
use Salient\Tests\Reflection\MyClassWithUnionsAndIntersections;
use Salient\Tests\Reflection\MyInterface;
use Salient\Tests\Reflection\MyOneLineClass;
use Salient\Tests\Reflection\MyOneLineTrait;
use Salient\Tests\Reflection\MyOtherInterface;
use Salient\Tests\Reflection\MyReusedTrait;
use Salient\Tests\Reflection\MySubclass;
use Salient\Tests\Reflection\MyTrait;
use Salient\Tests\Reflection\MyTraitAdaptationClass;
use Salient\Tests\Reflection\MyTraitAdaptationInterface;
use Salient\Tests\Reflection\MyUndocumentedClass;
use Salient\Tests\TestCase;
use Generator;
use LogicException;
use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;

/**
 * @covers \Salient\PHPDoc\PHPDocUtil
 */
final class PHPDocUtilTest extends TestCase
{
    /**
     * @dataProvider getAllClassDocCommentsProvider
     *
     * @param array<class-string,string|null> $expected
     * @param ReflectionClass<object> $class
     */
    public function testGetAllClassDocComments(
        array $expected,
        ReflectionClass $class,
        bool $includeAll = false
    ): void {
        $actual = PHPDocUtil::getAllClassDocComments($class, $includeAll);
        $this->assertSame($expected, $actual);
    }

    /**
     * @return array<array{array<class-string,string|null>,ReflectionClass<object>,2?:bool}>
     */
    public static function getAllClassDocCommentsProvider(): array
    {
        return [
            [
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
                new ReflectionClass(MySubclass::class),
            ],
            [
                [
                    MySubclass::class => "/**\n * MySubclass\n */",
                    MyUndocumentedClass::class => null,
                    MyClass::class => "/**\n * MyClass\n */",
                    MyTrait::class => "/**\n * MyTrait\n */",
                    MyBaseTrait::class => "/**\n * MyBaseTrait\n */",
                    MyReusedTrait::class => "/**\n * MyReusedTrait\n */",
                    MyBaseClass::class => "/**\n * MyBaseClass\n */",
                    MyInterface::class => "/**\n * MyInterface\n */",
                    MyBaseInterface::class => "/**\n * MyBaseInterface\n */",
                    MyOtherInterface::class => "/**\n * MyOtherInterface\n */",
                ],
                new ReflectionClass(MySubclass::class),
                true,
            ],
            [
                [
                    MyClass::class => "/**\n * MyClass\n */",
                    MyTrait::class => "/**\n * MyTrait\n */",
                    MyBaseTrait::class => "/**\n * MyBaseTrait\n */",
                    MyReusedTrait::class => "/**\n * MyReusedTrait\n */",
                    MyBaseClass::class => "/**\n * MyBaseClass\n */",
                    MyInterface::class => "/**\n * MyInterface\n */",
                    MyBaseInterface::class => "/**\n * MyBaseInterface\n */",
                ],
                new ReflectionClass(MyUndocumentedClass::class),
            ],
            [
                [
                    MyUndocumentedClass::class => null,
                    MyClass::class => "/**\n * MyClass\n */",
                    MyTrait::class => "/**\n * MyTrait\n */",
                    MyBaseTrait::class => "/**\n * MyBaseTrait\n */",
                    MyReusedTrait::class => "/**\n * MyReusedTrait\n */",
                    MyBaseClass::class => "/**\n * MyBaseClass\n */",
                    MyInterface::class => "/**\n * MyInterface\n */",
                    MyBaseInterface::class => "/**\n * MyBaseInterface\n */",
                ],
                new ReflectionClass(MyUndocumentedClass::class),
                true,
            ],
            [
                [
                    MyInterface::class => "/**\n * MyInterface\n */",
                    MyBaseInterface::class => "/**\n * MyBaseInterface\n */",
                ],
                new ReflectionClass(MyInterface::class),
            ],
        ];
    }

    /**
     * @dataProvider getAllMethodDocCommentsProvider
     *
     * @param ReflectionClass<object>|null $fromClass
     * @param array<string,string|null> $expected
     * @param array<string,string|null>|null $expectedClassDocComments
     */
    public function testGetAllMethodDocComments(
        ReflectionMethod $method,
        ?ReflectionClass $fromClass,
        array $expected,
        ?array $expectedClassDocComments = null
    ): void {
        if ($expectedClassDocComments === null) {
            $comments = PHPDocUtil::getAllMethodDocComments($method, $fromClass);
            $this->assertSame($expected, $comments, 'comments');
            return;
        }

        $comments = PHPDocUtil::getAllMethodDocComments($method, $fromClass, $classDocComments);
        $this->assertSame($expected, $comments, 'comments');
        $this->assertSame($expectedClassDocComments, $classDocComments, 'classDocComments');
    }

    /**
     * @return array<string,array{ReflectionMethod,ReflectionClass<object>|null,array<string,string|null>,3?:array<string,string|null>}>
     */
    public static function getAllMethodDocCommentsProvider(): array
    {
        $expected1 = [
            MySubclass::class => "/**\n     * MySubclass::MyDocumentedMethod() PHPDoc\n     *\n     * @return mixed\n     */",
            MyUndocumentedClass::class => "/**\n     * MyUndocumentedClass::MyDocumentedMethod() PHPDoc\n     *\n     * @return mixed\n     */",
            MyClass::class => "/**\n     * MyClass::MyDocumentedMethod() PHPDoc\n     *\n     * @return mixed\n     */",
            MyTrait::class => "/**\n     * MyTrait::MyDocumentedMethod() PHPDoc\n     *\n     * @return mixed\n     */",
            MyBaseTrait::class => "/**\n     * MyBaseTrait::MyDocumentedMethod() PHPDoc\n     *\n     * @return mixed\n     */",
            MyReusedTrait::class => "/**\n     * MyReusedTrait::MyDocumentedMethod() PHPDoc\n     *\n     * @return mixed\n     */",
            MyBaseClass::class => "/**\n     * MyBaseClass::MyDocumentedMethod() PHPDoc\n     *\n     * @return mixed\n     */",
            MyInterface::class => "/**\n     * MyInterface::MyDocumentedMethod() PHPDoc\n     *\n     * @return mixed\n     */",
            MyBaseInterface::class => "/**\n     * MyBaseInterface::MyDocumentedMethod() PHPDoc\n     *\n     * @return mixed\n     */",
            MyOtherInterface::class => "/**\n     * MyOtherInterface::MyDocumentedMethod() PHPDoc\n     *\n     * @return mixed\n     */",
        ];

        $expected2 = [
            MyBaseTrait::class => "/**\n     * MyBaseTrait::MySparselyDocumentedMethod() PHPDoc\n     *\n     * @return mixed\n     */",
            MyBaseInterface::class => "/**\n     * MyBaseInterface::MySparselyDocumentedMethod() PHPDoc\n     *\n     * @return mixed\n     */",
        ];

        $expected3 = [
            MySubclass::class => null,
            MyUndocumentedClass::class => null,
            MyClass::class => null,
            MyTrait::class => null,
            MyBaseTrait::class => "/**\n     * MyBaseTrait::MySparselyDocumentedMethod() PHPDoc\n     *\n     * @return mixed\n     */",
            MyInterface::class => null,
            MyBaseInterface::class => "/**\n     * MyBaseInterface::MySparselyDocumentedMethod() PHPDoc\n     *\n     * @return mixed\n     */",
        ];

        return [
            MySubclass::class . '::MyDocumentedMethod()' => [
                new ReflectionMethod(MySubclass::class, 'MyDocumentedMethod'),
                null,
                $expected1,
            ],
            MySubclass::class . '::MyDocumentedMethod() + classDocComments' => [
                new ReflectionMethod(MySubclass::class, 'MyDocumentedMethod'),
                null,
                $expected1,
                [
                    MySubclass::class => "/**\n * MySubclass\n */",
                    MyUndocumentedClass::class => null,
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
            MySubclass::class . '::MySparselyDocumentedMethod()' => [
                new ReflectionMethod(MySubclass::class, 'MySparselyDocumentedMethod'),
                null,
                $expected2,
            ],
            MySubclass::class . '::MySparselyDocumentedMethod() + classDocComments' => [
                new ReflectionMethod(MySubclass::class, 'MySparselyDocumentedMethod'),
                null,
                $expected2,
                [
                    MyBaseTrait::class => "/**\n * MyBaseTrait\n */",
                    MyBaseInterface::class => "/**\n * MyBaseInterface\n */",
                ],
            ],
            MySubclass::class . '::MySparselyDocumentedMethod() + fromClass' => [
                new ReflectionMethod(MySubclass::class, 'MySparselyDocumentedMethod'),
                new ReflectionClass(MySubclass::class),
                $expected3,
            ],
            MySubclass::class . '::MySparselyDocumentedMethod() + fromClass + classDocComments' => [
                new ReflectionMethod(MySubclass::class, 'MySparselyDocumentedMethod'),
                new ReflectionClass(MySubclass::class),
                $expected3,
                [
                    MySubclass::class => "/**\n * MySubclass\n */",
                    MyUndocumentedClass::class => null,
                    MyClass::class => "/**\n * MyClass\n */",
                    MyTrait::class => "/**\n * MyTrait\n */",
                    MyBaseTrait::class => "/**\n * MyBaseTrait\n */",
                    MyInterface::class => "/**\n * MyInterface\n */",
                    MyBaseInterface::class => "/**\n * MyBaseInterface\n */",
                ],
            ],
            MySubclass::class . '::MyTraitOnlyMethod()' => [
                new ReflectionMethod(MySubclass::class, 'MyTraitOnlyMethod'),
                new ReflectionClass(MySubclass::class),
                [
                    MySubclass::class => null,
                    MyUndocumentedClass::class => null,
                    MyClass::class => null,
                    MyTrait::class => "/**\n     * MyTrait::MyTraitOnlyMethod() PHPDoc\n     */",
                ],
            ],
            MyInterface::class . '::MySparselyDocumentedMethod()' => [
                new ReflectionMethod(MyInterface::class, 'MySparselyDocumentedMethod'),
                null,
                [
                    MyBaseInterface::class => "/**\n     * MyBaseInterface::MySparselyDocumentedMethod() PHPDoc\n     *\n     * @return mixed\n     */",
                ],
            ],
            MyInterface::class . '::MySparselyDocumentedMethod() + fromClass' => [
                new ReflectionMethod(MyInterface::class, 'MySparselyDocumentedMethod'),
                new ReflectionClass(MyInterface::class),
                [
                    MyInterface::class => null,
                    MyBaseInterface::class => "/**\n     * MyBaseInterface::MySparselyDocumentedMethod() PHPDoc\n     *\n     * @return mixed\n     */",
                ],
            ],
            MyTraitAdaptationClass::class . '::MyAdaptableMethod()' => [
                new ReflectionMethod(MyTraitAdaptationClass::class, 'MyAdaptableMethod'),
                null,
                [
                    MyBaseTrait::class => "/**\n     * MyBaseTrait::Adaptable() PHPDoc\n     *\n     * @return mixed\n     */",
                    MyTraitAdaptationInterface::class => "/**\n     * MyTraitAdaptationInterface::MyAdaptableMethod() PHPDoc\n     *\n     * @return mixed\n     */",
                ],
            ],
            MyTraitAdaptationClass::class . '::MyAdaptableMethod() + fromClass' => [
                new ReflectionMethod(MyTraitAdaptationClass::class, 'MyAdaptableMethod'),
                new ReflectionClass(MyTraitAdaptationClass::class),
                [
                    MyTraitAdaptationClass::class => null,
                    MyBaseTrait::class => "/**\n     * MyBaseTrait::Adaptable() PHPDoc\n     *\n     * @return mixed\n     */",
                    MyTraitAdaptationInterface::class => "/**\n     * MyTraitAdaptationInterface::MyAdaptableMethod() PHPDoc\n     *\n     * @return mixed\n     */",
                ],
            ],
            MyTraitAdaptationClass::class . '::Adaptable()' => [
                new ReflectionMethod(MyTraitAdaptationClass::class, 'Adaptable'),
                null,
                [
                    MyTraitAdaptationClass::class => "/**\n     * MyTraitAdaptationClass::Adaptable() PHPDoc\n     *\n     * @return mixed\n     */",
                    MyBaseTrait::class => "/**\n     * MyBaseTrait::Adaptable() PHPDoc\n     *\n     * @return mixed\n     */",
                ],
            ],
        ];
    }

    public function testGetAllMethodDocCommentsFromOneLineDeclaration(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage(sprintf(
            'Unable to check location of %s::%s(): %s::%s() declared on same line',
            MyOneLineClass::class,
            \PHP_VERSION_ID < 80000 ? 'Method' : 'MyOneLineMethod',
            MyOneLineTrait::class,
            \PHP_VERSION_ID < 80000 ? 'Method' : 'MyMethod',
        ));
        PHPDocUtil::getAllMethodDocComments(new ReflectionMethod(MyOneLineClass::class, 'MyOneLineMethod'));
    }

    /**
     * @dataProvider getAllPropertyDocCommentsProvider
     *
     * @param array<string,string> $expected
     * @param array<string,string>|null $expectedClassDocComments
     */
    public function testGetAllPropertyDocComments(
        ReflectionProperty $property,
        array $expected,
        ?array $expectedClassDocComments = null
    ): void {
        if ($expectedClassDocComments === null) {
            $comments = PHPDocUtil::getAllPropertyDocComments($property);
            $this->assertSame($expected, $comments);
            return;
        }

        $comments = PHPDocUtil::getAllPropertyDocComments($property, null, $classDocComments);
        $this->assertSame($expected, $comments);
        $this->assertSame($expectedClassDocComments, $classDocComments);
    }

    /**
     * @return array<string,array{0:ReflectionProperty,1:array<string,string>,2?:array<string,string>}>
     */
    public static function getAllPropertyDocCommentsProvider(): array
    {
        $expected = [
            MySubclass::class => "/**\n     * MySubclass::\$MyDocumentedProperty PHPDoc\n     *\n     * @var mixed\n     */",
            MyClass::class => "/**\n     * MyClass::\$MyDocumentedProperty PHPDoc\n     *\n     * @var mixed\n     */",
            MyTrait::class => "/**\n     * MyTrait::\$MyDocumentedProperty PHPDoc\n     *\n     * @var mixed\n     */",
            MyBaseTrait::class => "/**\n     * MyBaseTrait::\$MyDocumentedProperty PHPDoc\n     *\n     * @var mixed\n     */",
            MyReusedTrait::class => "/**\n     * MyReusedTrait::\$MyDocumentedProperty PHPDoc\n     *\n     * @var mixed\n     */",
            MyBaseClass::class => "/**\n     * MyBaseClass::\$MyDocumentedProperty PHPDoc\n     *\n     * @var mixed\n     */",
        ];

        return [
            MySubclass::class . '::$MyDocumentedProperty' => [
                new ReflectionProperty(MySubclass::class, 'MyDocumentedProperty'),
                $expected,
            ],
            MySubclass::class . '::$MyDocumentedProperty + classDocComments' => [
                new ReflectionProperty(MySubclass::class, 'MyDocumentedProperty'),
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
     * @dataProvider getTypeDeclarationProvider
     *
     * @param string[] $expected
     */
    public function testGetTypeDeclaration(
        array $expected,
        string $class,
        string $method,
        bool $phpDoc = false
    ): void {
        $method = new ReflectionMethod($class, $method);
        $types = [];
        foreach ($method->getParameters() as $param) {
            $types[] = PHPDocUtil::getTypeDeclaration(
                $param->getType(),
                '\\',
                fn($name) => $name === MyClass::class ? 'MyClass' : null,
                $phpDoc,
            );
        }
        $this->assertSame($expected, $types);
    }

    /**
     * @return Generator<string,array{string[],string,string,3?:bool}>
     */
    public static function getTypeDeclarationProvider(): Generator
    {
        yield from [
            'MyClass::__construct()' => [
                [
                    '',
                    '?int',
                    'string',
                    '?MyClass',
                    '?MyClass',
                ],
                MyClass::class,
                '__construct',
            ],
            'MyClass::__construct() [phpDoc]' => [
                [
                    '',
                    'int|null',
                    'string',
                    'MyClass|null',
                    'MyClass|null',
                ],
                MyClass::class,
                '__construct',
                true,
            ],
        ];

        if (\PHP_VERSION_ID >= 80100) {
            yield 'MyClassWithUnionsAndIntersections::MyMethod()' => [
                [
                    '',
                    '?int',
                    'string',
                    '\Countable&\ArrayAccess',
                    '\Salient\Tests\Reflection\MyBaseClass',
                    '?MyClass',
                    '?MyClass',
                    '?MyClass',
                    'string',
                    'MyClass|string',
                    'MyClass|string|null',
                    'MyClass|array',
                    'MyClass|string|null',
                    'string',
                ],
                MyClassWithUnionsAndIntersections::class,
                'MyMethod',
            ];
        }

        if (\PHP_VERSION_ID >= 80200) {
            yield 'MyClassWithDnfTypes::MyMethod()' => [
                [
                    '',
                    'null',
                    '?int',
                    'string',
                    '\Countable&\ArrayAccess',
                    '\Salient\Tests\Reflection\MyBaseClass',
                    '?MyClass',
                    '?MyClass',
                    '(MyClass&\Countable)|(MyClass&\ArrayAccess)',
                    '?MyClass',
                    'string',
                    'MyClass|string',
                    'MyClass|string|null',
                    'MyClass|array',
                    'MyClass|string|null',
                    'MyClass|(\Countable&\ArrayAccess)|string',
                    'MyClass|(\Countable&\ArrayAccess)|string|null',
                    'MyClass|(\Countable&\ArrayAccess)|array',
                    'MyClass|(\Countable&\ArrayAccess)|string|null',
                    'string',
                ],
                MyClassWithDnfTypes::class,
                'MyMethod',
            ];
        }
    }

    /**
     * @dataProvider getParameterDeclarationProvider
     *
     * @param string[] $expected
     */
    public function testGetParameterDeclaration(array $expected, string $class, string $method): void
    {
        $method = new ReflectionMethod($class, $method);
        $params = [];
        foreach ($method->getParameters() as $param) {
            $params[] = PHPDocUtil::getParameterDeclaration(
                $param,
                '',
                fn($name) => $name === MyClass::class ? 'MyClass' : null,
            );
        }
        $this->assertSame($expected, $params);
    }

    /**
     * @return Generator<string,array{string[],string,string}>
     */
    public static function getParameterDeclarationProvider(): Generator
    {
        yield 'MyClass::__construct()' => [
            [
                '$id',
                '?int $altId',
                'string $name',
                '?MyClass $parent',
                '?MyClass $altParent = null',
            ],
            MyClass::class,
            '__construct',
        ];

        if (\PHP_VERSION_ID >= 80100) {
            yield 'MyClassWithUnionsAndIntersections::MyMethod()' => [
                [
                    '$mixed',
                    '?int $nullableInt',
                    'string $string',
                    'Countable&ArrayAccess $intersection',
                    'Salient\Tests\Reflection\MyBaseClass $class',
                    '?MyClass $nullableClass',
                    '?MyClass &$nullableClassByRef',
                    '?MyClass $nullableAndOptionalClass = null',
                    'string $optionalString = MyClass::MY_CONSTANT',
                    'MyClass|string $union = SELF::MY_CONSTANT',
                    "MyClass|string|null \$nullableUnion = 'literal'",
                    "MyClass|array \$optionalArrayUnion = ['key'=>'value']",
                    'MyClass|string|null &$nullableUnionByRef = null',
                    'string &...$variadicByRef',
                ],
                MyClassWithUnionsAndIntersections::class,
                'MyMethod',
            ];
        }

        if (\PHP_VERSION_ID >= 80200) {
            yield 'MyClassWithDnfTypes::MyMethod()' => [
                [
                    '$mixed',
                    'null $null',
                    '?int $nullableInt',
                    'string $string',
                    'Countable&ArrayAccess $intersection',
                    'Salient\Tests\Reflection\MyBaseClass $class',
                    '?MyClass $nullableClass',
                    '?MyClass &$nullableClassByRef',
                    '(MyClass&Countable)|(MyClass&ArrayAccess) &$dnfByRef',
                    '?MyClass $nullableAndOptionalClass = null',
                    'string $optionalString = MyClass::MY_CONSTANT',
                    'MyClass|string $union = SELF::MY_CONSTANT',
                    "MyClass|string|null \$nullableUnion = 'literal'",
                    "MyClass|array \$optionalArrayUnion = ['key'=>'value']",
                    'MyClass|string|null &$nullableUnionByRef = null',
                    'MyClass|(Countable&ArrayAccess)|string $dnf = SELF::MY_CONSTANT',
                    "MyClass|(Countable&ArrayAccess)|string|null \$nullableDnf = 'literal'",
                    "MyClass|(Countable&ArrayAccess)|array \$optionalArrayDnf = ['key'=>'value']",
                    'MyClass|(Countable&ArrayAccess)|string|null &$nullableDnfByRef = null',
                    'string &...$variadicByRef',
                ],
                MyClassWithDnfTypes::class,
                'MyMethod',
            ];
        }
    }
}
