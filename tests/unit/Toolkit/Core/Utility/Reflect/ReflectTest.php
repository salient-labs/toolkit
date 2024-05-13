<?php declare(strict_types=1);

namespace Salient\Tests\Core\Utility\Reflect;

use Salient\Core\Utility\Reflect;
use Salient\Tests\TestCase;
use Generator;
use ReflectionClass;
use ReflectionClassConstant;
use ReflectionMethod;
use ReflectionParameter;
use ReflectionProperty;

/**
 * @covers \Salient\Core\Utility\Reflect
 */
final class ReflectTest extends TestCase
{
    public function testGetNames(): void
    {
        $expected = [
            'Salient\Tests\Core\Utility\Reflect\MyClass',
            'Salient\Tests\Core\Utility\Reflect\MyInterface',
            'Salient\Tests\Core\Utility\Reflect\MyTrait',
            'MY_CONSTANT',
            'MyDocumentedMethod',
            'parent',
            'MyDocumentedProperty',
        ];

        $names = Reflect::getNames([
            new ReflectionClass(MyClass::class),
            new ReflectionClass(MyInterface::class),
            new ReflectionClass(MyTrait::class),
            new ReflectionClassConstant(MyClass::class, 'MY_CONSTANT'),
            new ReflectionMethod(MyClass::class, 'MyDocumentedMethod'),
            new ReflectionParameter([MyClass::class, '__construct'], 'parent'),
            new ReflectionProperty(MyClass::class, 'MyDocumentedProperty'),
        ]);

        $this->assertSame($expected, $names);
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
     * @dataProvider getAllTypesProvider
     *
     * @param array<string[]> $expected
     */
    public function testGetAllTypes(array $expected, string $class, string $method): void
    {
        $method = new ReflectionMethod($class, $method);
        $allTypes = [];
        foreach ($method->getParameters() as $param) {
            $types = [];
            foreach (Reflect::getAllTypes($param->getType()) as $type) {
                $types[] = $type->getName();
            }
            $allTypes[] = $types;
        }
        $this->assertSame($expected, $allTypes);
    }

    /**
     * @dataProvider getAllTypesProvider
     *
     * @param array<string[]> $expected
     */
    public function testGetAllTypeNames(array $expected, string $class, string $method): void
    {
        $method = new ReflectionMethod($class, $method);
        $allTypes = [];
        foreach ($method->getParameters() as $param) {
            $allTypes[] = Reflect::getAllTypeNames($param->getType());
        }
        $this->assertSame($expected, $allTypes);
    }

    /**
     * @return Generator<string,array{array<string[]>,string,string}>
     */
    public static function getAllTypesProvider(): Generator
    {
        yield 'MyClass::__construct()' => [
            [
                [],
                ['int'],
                ['string'],
                ['Salient\Tests\Core\Utility\Reflect\MyClass'],
                ['Salient\Tests\Core\Utility\Reflect\MyClass'],
            ],
            MyClass::class,
            '__construct',
        ];

        if (\PHP_VERSION_ID >= 80100) {
            yield 'MyClassWithUnionsAndIntersections::MyMethod()' => [
                [
                    [],
                    ['int'],
                    ['string'],
                    ['Countable', 'ArrayAccess'],
                    ['Salient\Tests\Core\Utility\Reflect\MyBaseClass'],
                    ['Salient\Tests\Core\Utility\Reflect\MyClass'],
                    ['Salient\Tests\Core\Utility\Reflect\MyClass'],
                    ['Salient\Tests\Core\Utility\Reflect\MyClass'],
                    ['string'],
                    ['Salient\Tests\Core\Utility\Reflect\MyClass', 'string'],
                    ['Salient\Tests\Core\Utility\Reflect\MyClass', 'string', 'null'],
                    ['Salient\Tests\Core\Utility\Reflect\MyClass', 'array'],
                    ['Salient\Tests\Core\Utility\Reflect\MyClass', 'string', 'null'],
                    ['string'],
                ],
                MyClassWithUnionsAndIntersections::class,
                'MyMethod',
            ];
        }

        if (\PHP_VERSION_ID >= 80200) {
            yield 'MyClassWithDnfTypes::MyMethod()' => [
                [
                    [],
                    ['int'],
                    ['string'],
                    ['Countable', 'ArrayAccess'],
                    ['Salient\Tests\Core\Utility\Reflect\MyBaseClass'],
                    ['Salient\Tests\Core\Utility\Reflect\MyClass'],
                    ['Salient\Tests\Core\Utility\Reflect\MyClass'],
                    ['Salient\Tests\Core\Utility\Reflect\MyClass'],
                    ['string'],
                    ['Salient\Tests\Core\Utility\Reflect\MyClass', 'string'],
                    ['Salient\Tests\Core\Utility\Reflect\MyClass', 'string', 'null'],
                    ['Salient\Tests\Core\Utility\Reflect\MyClass', 'array'],
                    ['Salient\Tests\Core\Utility\Reflect\MyClass', 'string', 'null'],
                    ['Salient\Tests\Core\Utility\Reflect\MyClass', 'Countable', 'ArrayAccess', 'string'],
                    ['Salient\Tests\Core\Utility\Reflect\MyClass', 'Countable', 'ArrayAccess', 'string', 'null'],
                    ['Salient\Tests\Core\Utility\Reflect\MyClass', 'Countable', 'ArrayAccess', 'array'],
                    ['Salient\Tests\Core\Utility\Reflect\MyClass', 'Countable', 'ArrayAccess', 'string', 'null'],
                    ['Salient\Tests\Core\Utility\Reflect\MyClass', 'Countable', 'ArrayAccess', 'null'],
                    ['string'],
                ],
                MyClassWithDnfTypes::class,
                'MyMethod',
            ];
        }
    }

    /**
     * @dataProvider getAllClassDocCommentsProvider
     *
     * @param ReflectionClass<object> $class
     * @param array<string,string> $expected
     */
    public function testGetAllClassDocComments(ReflectionClass $class, array $expected): void
    {
        $comments = Reflect::getAllClassDocComments($class);
        $this->assertSame($expected, $comments);
    }

    /**
     * @return array<string,array{ReflectionClass<object>,array<string,string>}>
     */
    public static function getAllClassDocCommentsProvider(): array
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
            $comments = Reflect::getAllMethodDocComments($method, $fromClass);
            $this->assertSame($expected, $comments, 'comments');
            return;
        }

        $comments = Reflect::getAllMethodDocComments($method, $fromClass, $classDocComments);
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
                ]
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
                ]
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
        ];
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
            $comments = Reflect::getAllPropertyDocComments($property);
            $this->assertSame($expected, $comments);
            return;
        }

        $comments = Reflect::getAllPropertyDocComments($property, $classDocComments);
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
    public function testGetTypeDeclaration(array $expected, string $class, string $method): void
    {
        $method = new ReflectionMethod($class, $method);
        $types = [];
        foreach ($method->getParameters() as $param) {
            $types[] = Reflect::getTypeDeclaration(
                $param->getType(),
                '\\',
                fn($name) => $name === MyClass::class ? 'MyClass' : null,
            );
        }
        $this->assertSame($expected, $types);
    }

    /**
     * @return Generator<string,array{string[],string,string}>
     */
    public static function getTypeDeclarationProvider(): Generator
    {
        yield 'MyClass::__construct()' => [
            [
                '',
                '?int',
                'string',
                '?MyClass',
                '?MyClass',
            ],
            MyClass::class,
            '__construct',
        ];

        if (\PHP_VERSION_ID >= 80100) {
            yield 'MyClassWithUnionsAndIntersections::MyMethod()' => [
                [
                    '',
                    '?int',
                    'string',
                    '\Countable&\ArrayAccess',
                    '\Salient\Tests\Core\Utility\Reflect\MyBaseClass',
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
                    '?int',
                    'string',
                    '\Countable&\ArrayAccess',
                    '\Salient\Tests\Core\Utility\Reflect\MyBaseClass',
                    '?MyClass',
                    '?MyClass',
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
                    '(MyClass&\Countable)|(MyClass&\ArrayAccess)|null',
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
            $params[] = Reflect::getParameterDeclaration(
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
                    'Salient\Tests\Core\Utility\Reflect\MyBaseClass $class',
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
                    '?int $nullableInt',
                    'string $string',
                    'Countable&ArrayAccess $intersection',
                    'Salient\Tests\Core\Utility\Reflect\MyBaseClass $class',
                    '?MyClass $nullableClass',
                    '?MyClass &$nullableClassByRef',
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
                    '(MyClass&Countable)|(MyClass&ArrayAccess)|null &$dnfByRef = null',
                    'string &...$variadicByRef',
                ],
                MyClassWithDnfTypes::class,
                'MyMethod',
            ];
        }
    }
}

/**
 * MyBaseInterface
 */
interface MyBaseInterface
{
    /**
     * MyBaseInterface::MyDocumentedMethod() PHPDoc
     *
     * @return mixed
     */
    public function MyDocumentedMethod();

    /**
     * MyBaseInterface::MySparselyDocumentedMethod() PHPDoc
     *
     * @return mixed
     */
    public function MySparselyDocumentedMethod();
}

/**
 * MyInterface
 */
interface MyInterface extends MyBaseInterface
{
    /**
     * MyInterface::MyDocumentedMethod() PHPDoc
     *
     * @return mixed
     */
    public function MyDocumentedMethod();
}

/**
 * MyOtherInterface
 */
interface MyOtherInterface
{
    /**
     * MyOtherInterface::MyDocumentedMethod() PHPDoc
     *
     * @return mixed
     */
    public function MyDocumentedMethod();
}

/**
 * MyReusedTrait
 */
trait MyReusedTrait
{
    /**
     * MyReusedTrait::$MyDocumentedProperty PHPDoc
     *
     * @var mixed
     */
    public $MyDocumentedProperty;

    /**
     * MyReusedTrait::MyDocumentedMethod() PHPDoc
     *
     * @return mixed
     */
    public function MyDocumentedMethod() {}
}

/**
 * MyBaseTrait
 */
trait MyBaseTrait
{
    use MyReusedTrait;

    /**
     * MyBaseTrait::$MyDocumentedProperty PHPDoc
     *
     * @var mixed
     */
    public $MyDocumentedProperty;

    /**
     * MyBaseTrait::MyDocumentedMethod() PHPDoc
     *
     * @return mixed
     */
    public function MyDocumentedMethod() {}

    /**
     * MyBaseTrait::MySparselyDocumentedMethod() PHPDoc
     *
     * @return mixed
     */
    public function MySparselyDocumentedMethod() {}
}

/**
 * MyTrait
 */
trait MyTrait
{
    use MyBaseTrait;

    /**
     * MyTrait::$MyDocumentedProperty PHPDoc
     *
     * @var mixed
     */
    public $MyDocumentedProperty;

    /**
     * MyTrait::MyDocumentedMethod() PHPDoc
     *
     * @return mixed
     */
    public function MyDocumentedMethod() {}

    /**
     * MyTrait::MyTraitOnlyMethod() PHPDoc
     */
    public function MyTraitOnlyMethod(): void {}
}

/**
 * MyBaseClass
 */
abstract class MyBaseClass
{
    /**
     * MyBaseClass::$MyDocumentedProperty PHPDoc
     *
     * @var mixed
     */
    public $MyDocumentedProperty;

    /**
     * @var mixed
     * @phpstan-ignore-next-line
     */
    private $MyPrivateProperty1;

    /**
     * @var mixed
     * @phpstan-ignore-next-line
     */
    private $MyPrivateProperty2;

    /**
     * MyBaseClass::MyDocumentedMethod() PHPDoc
     *
     * @return mixed
     */
    public function MyDocumentedMethod() {}
}

/**
 * MyClass
 */
class MyClass extends MyBaseClass implements MyInterface
{
    use MyTrait;
    use MyReusedTrait;

    public const MY_CONSTANT = 'my constant';

    /** @var int|string */
    public $Id;
    /** @var int|null */
    public $AltId;
    /** @var string */
    public $Name;
    /** @var MyClass|null */
    public $Parent;
    /** @var MyClass|null */
    public $AltParent;
    /** @var mixed */
    protected $MyPrivateProperty2;

    /**
     * @param int|string $id
     */
    public function __construct($id, ?int $altId, string $name, ?MyClass $parent, MyClass $altParent = null)
    {
        $this->Id = $id;
        $this->AltId = $altId;
        $this->Name = $name;
        $this->Parent = $parent;
        $this->AltParent = $altParent;
    }

    /**
     * MyClass::$MyDocumentedProperty PHPDoc
     *
     * @var mixed
     */
    public $MyDocumentedProperty;

    /**
     * MyClass::MyDocumentedMethod() PHPDoc
     *
     * @return mixed
     */
    public function MyDocumentedMethod() {}

    public function MySparselyDocumentedMethod() {}
}

class MyUndocumentedClass extends MyClass
{
    /**
     * MyUndocumentedClass::MyDocumentedMethod() PHPDoc
     *
     * @return mixed
     */
    public function MyDocumentedMethod() {}
}

/**
 * MySubclass
 */
class MySubclass extends MyUndocumentedClass implements MyOtherInterface
{
    /**
     * MySubclass::$MyDocumentedProperty PHPDoc
     *
     * @var mixed
     */
    public $MyDocumentedProperty;

    /**
     * MySubclass::MyDocumentedMethod() PHPDoc
     *
     * @return mixed
     */
    public function MyDocumentedMethod() {}
}
