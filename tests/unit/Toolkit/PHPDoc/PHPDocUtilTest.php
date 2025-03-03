<?php declare(strict_types=1);

namespace Salient\Tests\PHPDoc;

use Salient\PHPDoc\PHPDocUtil;
use Salient\Sli\Internal\TokenExtractor;
use Salient\Tests\Reflection\MyBaseClass;
use Salient\Tests\Reflection\MyBaseInterface;
use Salient\Tests\Reflection\MyBaseTrait;
use Salient\Tests\Reflection\MyClass;
use Salient\Tests\Reflection\MyClassWithDnfTypes;
use Salient\Tests\Reflection\MyClassWithTraitWithConstants;
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
use Salient\Tests\Reflection\MyTraitWithConstants;
use Salient\Tests\Reflection\MyUndocumentedClass;
use Salient\Tests\TestCase;
use Salient\Utility\Arr;
use Salient\Utility\Get;
use ReflectionClass;
use ReflectionClassConstant;
use ReflectionException;
use ReflectionMethod;
use ReflectionProperty;

/**
 * @covers \Salient\PHPDoc\PHPDocUtil
 */
final class PHPDocUtilTest extends TestCase
{
    private const CLASS_DOC_COMMENTS = [
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
    ];

    /**
     * @dataProvider getAllClassDocCommentsProvider
     *
     * @param array<class-string,array<class-string,string|null>|string|null> $expected
     * @param ReflectionClass<*> $class
     */
    public function testGetAllClassDocComments(
        array $expected,
        ReflectionClass $class,
        bool $includeAll = false,
        bool $groupTraits = false
    ): void {
        $actual = PHPDocUtil::getAllClassDocComments($class, $includeAll, $groupTraits);
        $this->assertSame($expected, $actual, $this->getMessage($actual));
    }

    /**
     * @return array<array{array<class-string,array<class-string,string|null>|string|null>,ReflectionClass<*>,2?:bool,3?:bool}>
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
                self::CLASS_DOC_COMMENTS,
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
            [
                [
                    MySubclass::class => "/**\n * MySubclass\n */",
                    MyClass::class => [
                        MyClass::class => "/**\n * MyClass\n */",
                        MyTrait::class => "/**\n * MyTrait\n */",
                        MyBaseTrait::class => "/**\n * MyBaseTrait\n */",
                        MyReusedTrait::class => "/**\n * MyReusedTrait\n */",
                    ],
                    MyBaseClass::class => "/**\n * MyBaseClass\n */",
                    MyInterface::class => "/**\n * MyInterface\n */",
                    MyBaseInterface::class => "/**\n * MyBaseInterface\n */",
                    MyOtherInterface::class => "/**\n * MyOtherInterface\n */",
                ],
                new ReflectionClass(MySubclass::class),
                false,
                true,
            ],
            [
                [
                    MySubclass::class => "/**\n * MySubclass\n */",
                    MyUndocumentedClass::class => null,
                    MyClass::class => [
                        MyClass::class => "/**\n * MyClass\n */",
                        MyTrait::class => "/**\n * MyTrait\n */",
                        MyBaseTrait::class => "/**\n * MyBaseTrait\n */",
                        MyReusedTrait::class => "/**\n * MyReusedTrait\n */",
                    ],
                    MyBaseClass::class => "/**\n * MyBaseClass\n */",
                    MyInterface::class => "/**\n * MyInterface\n */",
                    MyBaseInterface::class => "/**\n * MyBaseInterface\n */",
                    MyOtherInterface::class => "/**\n * MyOtherInterface\n */",
                ],
                new ReflectionClass(MySubclass::class),
                true,
                true,
            ],
            [
                [
                    MyClass::class => [
                        MyClass::class => "/**\n * MyClass\n */",
                        MyTrait::class => "/**\n * MyTrait\n */",
                        MyBaseTrait::class => "/**\n * MyBaseTrait\n */",
                        MyReusedTrait::class => "/**\n * MyReusedTrait\n */",
                    ],
                    MyBaseClass::class => "/**\n * MyBaseClass\n */",
                    MyInterface::class => "/**\n * MyInterface\n */",
                    MyBaseInterface::class => "/**\n * MyBaseInterface\n */",
                ],
                new ReflectionClass(MyUndocumentedClass::class),
                false,
                true,
            ],
            [
                [
                    MyUndocumentedClass::class => null,
                    MyClass::class => [
                        MyClass::class => "/**\n * MyClass\n */",
                        MyTrait::class => "/**\n * MyTrait\n */",
                        MyBaseTrait::class => "/**\n * MyBaseTrait\n */",
                        MyReusedTrait::class => "/**\n * MyReusedTrait\n */",
                    ],
                    MyBaseClass::class => "/**\n * MyBaseClass\n */",
                    MyInterface::class => "/**\n * MyInterface\n */",
                    MyBaseInterface::class => "/**\n * MyBaseInterface\n */",
                ],
                new ReflectionClass(MyUndocumentedClass::class),
                true,
                true,
            ],
        ];
    }

    /**
     * @dataProvider getAllMethodDocCommentsProvider
     *
     * @param array<class-string,array<class-string,string|null>|string|null> $expected
     * @param array<class-string,string|null>|null $expectedClassDocComments
     * @param ReflectionClass<*>|null $fromClass
     */
    public function testGetAllMethodDocComments(
        array $expected,
        ?array $expectedClassDocComments,
        ReflectionMethod $method,
        ?ReflectionClass $fromClass = null,
        bool $groupTraits = false
    ): void {
        if ($expectedClassDocComments === null) {
            $actual = PHPDocUtil::getAllMethodDocComments($method, $fromClass, $groupTraits);
            $this->assertSame($expected, $actual, $this->getMessage($actual));
        } else {
            $actual = PHPDocUtil::getAllMethodDocComments($method, $fromClass, $groupTraits, $classDocComments);
            $this->assertSame($expected, $actual, $this->getMessage($actual));
            $this->assertSame($expectedClassDocComments, $classDocComments, $this->getMessage($classDocComments, '$expectedClassDocComments'));
        }
    }

    /**
     * @return array<array{array<class-string,array<class-string,string|null>|string|null>,array<class-string,string|null>|null,ReflectionMethod,3?:ReflectionClass<*>|null,4?:bool}>
     */
    public static function getAllMethodDocCommentsProvider(): array
    {
        $expected1 = [
            MySubclass::class => "/**\n     * MySubclass::MyDocumentedMethod()\n     *\n     * @return mixed\n     */",
            MyUndocumentedClass::class => "/**\n     * MyUndocumentedClass::MyDocumentedMethod()\n     *\n     * @return mixed\n     */",
            MyClass::class => "/**\n     * MyClass::MyDocumentedMethod()\n     *\n     * @return mixed\n     */",
            MyTrait::class => "/**\n     * MyTrait::MyDocumentedMethod()\n     *\n     * @return mixed\n     */",
            MyBaseTrait::class => "/**\n     * MyBaseTrait::MyDocumentedMethod()\n     *\n     * @return mixed\n     */",
            MyReusedTrait::class => "/**\n     * MyReusedTrait::MyDocumentedMethod()\n     *\n     * @return mixed\n     */",
            MyBaseClass::class => "/**\n     * MyBaseClass::MyDocumentedMethod()\n     *\n     * @return mixed\n     */",
            MyInterface::class => "/**\n     * MyInterface::MyDocumentedMethod()\n     *\n     * @return mixed\n     */",
            MyBaseInterface::class => "/**\n     * MyBaseInterface::MyDocumentedMethod()\n     *\n     * @return mixed\n     */",
            MyOtherInterface::class => "/**\n     * MyOtherInterface::MyDocumentedMethod()\n     *\n     * @return mixed\n     */",
        ];

        $expected2 = [
            MyBaseTrait::class => "/**\n     * MyBaseTrait::MySparselyDocumentedMethod()\n     *\n     * @return mixed\n     */",
            MyBaseInterface::class => "/**\n     * MyBaseInterface::MySparselyDocumentedMethod()\n     *\n     * @return mixed\n     */",
        ];

        $expected2c = [
            MyBaseTrait::class => "/**\n * MyBaseTrait\n */",
            MyBaseInterface::class => "/**\n * MyBaseInterface\n */",
        ];

        $expected3 = [
            MySubclass::class => null,
            MyUndocumentedClass::class => null,
            MyClass::class => null,
            MyTrait::class => null,
            MyBaseTrait::class => "/**\n     * MyBaseTrait::MySparselyDocumentedMethod()\n     *\n     * @return mixed\n     */",
            MyInterface::class => null,
            MyBaseInterface::class => "/**\n     * MyBaseInterface::MySparselyDocumentedMethod()\n     *\n     * @return mixed\n     */",
        ];

        $expected3c = [
            MySubclass::class => "/**\n * MySubclass\n */",
            MyUndocumentedClass::class => null,
            MyClass::class => "/**\n * MyClass\n */",
            MyTrait::class => "/**\n * MyTrait\n */",
            MyBaseTrait::class => "/**\n * MyBaseTrait\n */",
            MyInterface::class => "/**\n * MyInterface\n */",
            MyBaseInterface::class => "/**\n * MyBaseInterface\n */",
        ];

        $expected4 = [
            MySubclass::class => "/**\n     * MySubclass::MyDocumentedMethod()\n     *\n     * @return mixed\n     */",
            MyUndocumentedClass::class => "/**\n     * MyUndocumentedClass::MyDocumentedMethod()\n     *\n     * @return mixed\n     */",
            MyClass::class => [
                MyClass::class => "/**\n     * MyClass::MyDocumentedMethod()\n     *\n     * @return mixed\n     */",
                MyTrait::class => "/**\n     * MyTrait::MyDocumentedMethod()\n     *\n     * @return mixed\n     */",
                MyBaseTrait::class => "/**\n     * MyBaseTrait::MyDocumentedMethod()\n     *\n     * @return mixed\n     */",
                MyReusedTrait::class => "/**\n     * MyReusedTrait::MyDocumentedMethod()\n     *\n     * @return mixed\n     */",
            ],
            MyBaseClass::class => "/**\n     * MyBaseClass::MyDocumentedMethod()\n     *\n     * @return mixed\n     */",
            MyInterface::class => "/**\n     * MyInterface::MyDocumentedMethod()\n     *\n     * @return mixed\n     */",
            MyBaseInterface::class => "/**\n     * MyBaseInterface::MyDocumentedMethod()\n     *\n     * @return mixed\n     */",
            MyOtherInterface::class => "/**\n     * MyOtherInterface::MyDocumentedMethod()\n     *\n     * @return mixed\n     */",
        ];

        $expected5 = [
            MyClass::class => [
                MyBaseTrait::class => "/**\n     * MyBaseTrait::MySparselyDocumentedMethod()\n     *\n     * @return mixed\n     */",
            ],
            MyBaseInterface::class => "/**\n     * MyBaseInterface::MySparselyDocumentedMethod()\n     *\n     * @return mixed\n     */",
        ];

        $expected6 = [
            MySubclass::class => null,
            MyUndocumentedClass::class => null,
            MyClass::class => [
                MyClass::class => null,
                MyTrait::class => null,
                MyBaseTrait::class => "/**\n     * MyBaseTrait::MySparselyDocumentedMethod()\n     *\n     * @return mixed\n     */",
            ],
            MyInterface::class => null,
            MyBaseInterface::class => "/**\n     * MyBaseInterface::MySparselyDocumentedMethod()\n     *\n     * @return mixed\n     */",
        ];

        return [
            [
                $expected1,
                null,
                new ReflectionMethod(MySubclass::class, 'MyDocumentedMethod'),
            ],
            [
                $expected1,
                self::CLASS_DOC_COMMENTS,
                new ReflectionMethod(MySubclass::class, 'MyDocumentedMethod'),
            ],
            [
                $expected2,
                null,
                new ReflectionMethod(MySubclass::class, 'MySparselyDocumentedMethod'),
            ],
            [
                $expected2,
                $expected2c,
                new ReflectionMethod(MySubclass::class, 'MySparselyDocumentedMethod'),
            ],
            [
                $expected3,
                null,
                new ReflectionMethod(MySubclass::class, 'MySparselyDocumentedMethod'),
                new ReflectionClass(MySubclass::class),
            ],
            [
                $expected3,
                $expected3c,
                new ReflectionMethod(MySubclass::class, 'MySparselyDocumentedMethod'),
                new ReflectionClass(MySubclass::class),
            ],
            [
                [
                    MySubclass::class => null,
                    MyUndocumentedClass::class => null,
                    MyClass::class => null,
                    MyTrait::class => "/**\n     * MyTrait::MyTraitOnlyMethod()\n     */",
                ],
                null,
                new ReflectionMethod(MySubclass::class, 'MyTraitOnlyMethod'),
                new ReflectionClass(MySubclass::class),
            ],
            [
                [
                    MyBaseInterface::class => "/**\n     * MyBaseInterface::MySparselyDocumentedMethod()\n     *\n     * @return mixed\n     */",
                ],
                null,
                new ReflectionMethod(MyInterface::class, 'MySparselyDocumentedMethod'),
            ],
            [
                [
                    MyInterface::class => null,
                    MyBaseInterface::class => "/**\n     * MyBaseInterface::MySparselyDocumentedMethod()\n     *\n     * @return mixed\n     */",
                ],
                null,
                new ReflectionMethod(MyInterface::class, 'MySparselyDocumentedMethod'),
                new ReflectionClass(MyInterface::class),
            ],
            [
                [
                    MyBaseTrait::class => "/**\n     * MyBaseTrait::Adaptable()\n     *\n     * @return mixed\n     */",
                    MyTraitAdaptationInterface::class => "/**\n     * MyTraitAdaptationInterface::MyAdaptableMethod()\n     *\n     * @return mixed\n     */",
                ],
                null,
                new ReflectionMethod(MyTraitAdaptationClass::class, 'MyAdaptableMethod'),
            ],
            [
                [
                    MyTraitAdaptationClass::class => null,
                    MyBaseTrait::class => "/**\n     * MyBaseTrait::Adaptable()\n     *\n     * @return mixed\n     */",
                    MyTraitAdaptationInterface::class => "/**\n     * MyTraitAdaptationInterface::MyAdaptableMethod()\n     *\n     * @return mixed\n     */",
                ],
                null,
                new ReflectionMethod(MyTraitAdaptationClass::class, 'MyAdaptableMethod'),
                new ReflectionClass(MyTraitAdaptationClass::class),
            ],
            [
                [
                    MyTraitAdaptationClass::class => "/**\n     * MyTraitAdaptationClass::Adaptable()\n     *\n     * @return mixed\n     */",
                    MyBaseTrait::class => "/**\n     * MyBaseTrait::Adaptable()\n     *\n     * @return mixed\n     */",
                ],
                null,
                new ReflectionMethod(MyTraitAdaptationClass::class, 'Adaptable'),
            ],
            [
                [
                    MyUndocumentedClass::class => "/**\n     * MyUndocumentedClass::MyPrivateMethod()\n     */",
                ],
                null,
                new ReflectionMethod(MySubclass::class, 'MyPrivateMethod'),
            ],
            [
                [
                    MySubclass::class => null,
                    MyUndocumentedClass::class => "/**\n     * MyUndocumentedClass::MyPrivateMethod()\n     */",
                ],
                null,
                new ReflectionMethod(MySubclass::class, 'MyPrivateMethod'),
                new ReflectionClass(MySubclass::class),
            ],
            [
                [
                    MyClass::class => "/**\n     * MyClass::MyPrivateMethod()\n     */",
                    MyTrait::class => "/**\n     * MyTrait::MyPrivateMethod()\n     */",
                    MyBaseTrait::class => "/**\n     * MyBaseTrait::MyPrivateMethod()\n     */",
                    MyReusedTrait::class => "/**\n     * MyReusedTrait::MyPrivateMethod()\n     */",
                ],
                null,
                new ReflectionMethod(MyClass::class, 'MyPrivateMethod'),
            ],
            [
                $expected4,
                null,
                new ReflectionMethod(MySubclass::class, 'MyDocumentedMethod'),
                null,
                true,
            ],
            [
                $expected4,
                self::CLASS_DOC_COMMENTS,
                new ReflectionMethod(MySubclass::class, 'MyDocumentedMethod'),
                null,
                true,
            ],
            [
                $expected5,
                null,
                new ReflectionMethod(MySubclass::class, 'MySparselyDocumentedMethod'),
                null,
                true,
            ],
            [
                $expected5,
                $expected2c,
                new ReflectionMethod(MySubclass::class, 'MySparselyDocumentedMethod'),
                null,
                true,
            ],
            [
                $expected6,
                null,
                new ReflectionMethod(MySubclass::class, 'MySparselyDocumentedMethod'),
                new ReflectionClass(MySubclass::class),
                true,
            ],
            [
                $expected6,
                $expected3c,
                new ReflectionMethod(MySubclass::class, 'MySparselyDocumentedMethod'),
                new ReflectionClass(MySubclass::class),
                true,
            ],
            [
                [
                    MySubclass::class => null,
                    MyUndocumentedClass::class => null,
                    MyClass::class => [
                        MyClass::class => null,
                        MyTrait::class => "/**\n     * MyTrait::MyTraitOnlyMethod()\n     */",
                    ],
                ],
                null,
                new ReflectionMethod(MySubclass::class, 'MyTraitOnlyMethod'),
                new ReflectionClass(MySubclass::class),
                true,
            ],
            [
                [
                    MyTraitAdaptationClass::class => [
                        MyBaseTrait::class => "/**\n     * MyBaseTrait::Adaptable()\n     *\n     * @return mixed\n     */",
                    ],
                    MyTraitAdaptationInterface::class => "/**\n     * MyTraitAdaptationInterface::MyAdaptableMethod()\n     *\n     * @return mixed\n     */",
                ],
                null,
                new ReflectionMethod(MyTraitAdaptationClass::class, 'MyAdaptableMethod'),
                null,
                true,
            ],
            [
                [
                    MyTraitAdaptationClass::class => [
                        MyTraitAdaptationClass::class => null,
                        MyBaseTrait::class => "/**\n     * MyBaseTrait::Adaptable()\n     *\n     * @return mixed\n     */",
                    ],
                    MyTraitAdaptationInterface::class => "/**\n     * MyTraitAdaptationInterface::MyAdaptableMethod()\n     *\n     * @return mixed\n     */",
                ],
                null,
                new ReflectionMethod(MyTraitAdaptationClass::class, 'MyAdaptableMethod'),
                new ReflectionClass(MyTraitAdaptationClass::class),
                true,
            ],
            [
                [
                    MyTraitAdaptationClass::class => [
                        MyTraitAdaptationClass::class => "/**\n     * MyTraitAdaptationClass::Adaptable()\n     *\n     * @return mixed\n     */",
                        MyBaseTrait::class => "/**\n     * MyBaseTrait::Adaptable()\n     *\n     * @return mixed\n     */",
                    ],
                ],
                null,
                new ReflectionMethod(MyTraitAdaptationClass::class, 'Adaptable'),
                null,
                true,
            ],
            [
                [
                    MyClass::class => [
                        MyClass::class => "/**\n     * MyClass::MyPrivateMethod()\n     */",
                        MyTrait::class => "/**\n     * MyTrait::MyPrivateMethod()\n     */",
                        MyBaseTrait::class => "/**\n     * MyBaseTrait::MyPrivateMethod()\n     */",
                        MyReusedTrait::class => "/**\n     * MyReusedTrait::MyPrivateMethod()\n     */",
                    ],
                ],
                null,
                new ReflectionMethod(MyClass::class, 'MyPrivateMethod'),
                null,
                true,
            ],
        ];
    }

    public function testGetAllMethodDocCommentsFromOneLineDeclaration(): void
    {
        $this->expectException(ReflectionException::class);
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
     * @param array<class-string,array<class-string,string|null>|string|null> $expected
     * @param array<class-string,string|null>|null $expectedClassDocComments
     * @param ReflectionClass<*>|null $fromClass
     */
    public function testGetAllPropertyDocComments(
        array $expected,
        ?array $expectedClassDocComments,
        ReflectionProperty $property,
        ?ReflectionClass $fromClass = null,
        bool $groupTraits = false
    ): void {
        if ($expectedClassDocComments === null) {
            $actual = PHPDocUtil::getAllPropertyDocComments($property, $fromClass, $groupTraits);
            $this->assertSame($expected, $actual, $this->getMessage($actual));
        } else {
            $actual = PHPDocUtil::getAllPropertyDocComments($property, $fromClass, $groupTraits, $classDocComments);
            $this->assertSame($expected, $actual, $this->getMessage($actual));
            $this->assertSame($expectedClassDocComments, $classDocComments, $this->getMessage($classDocComments, '$expectedClassDocComments'));
        }
    }

    /**
     * @return array<array{array<class-string,array<class-string,string|null>|string|null>,array<class-string,string|null>|null,ReflectionProperty,3?:ReflectionClass<*>|null,4?:bool}>
     */
    public static function getAllPropertyDocCommentsProvider(): array
    {
        $expected1 = [
            MySubclass::class => "/**\n     * MySubclass::\$MyDocumentedProperty\n     *\n     * @var mixed\n     */",
            MyClass::class => "/**\n     * MyClass::\$MyDocumentedProperty\n     *\n     * @var mixed\n     */",
            MyTrait::class => "/**\n     * MyTrait::\$MyDocumentedProperty\n     *\n     * @var mixed\n     */",
            MyBaseTrait::class => "/**\n     * MyBaseTrait::\$MyDocumentedProperty\n     *\n     * @var mixed\n     */",
            MyReusedTrait::class => "/**\n     * MyReusedTrait::\$MyDocumentedProperty\n     *\n     * @var mixed\n     */",
            MyBaseClass::class => "/**\n     * MyBaseClass::\$MyDocumentedProperty\n     *\n     * @var mixed\n     */",
        ];

        $expected1c = [
            MySubclass::class => "/**\n * MySubclass\n */",
            MyClass::class => "/**\n * MyClass\n */",
            MyTrait::class => "/**\n * MyTrait\n */",
            MyBaseTrait::class => "/**\n * MyBaseTrait\n */",
            MyReusedTrait::class => "/**\n * MyReusedTrait\n */",
            MyBaseClass::class => "/**\n * MyBaseClass\n */",
        ];

        $expected2 = [
            MySubclass::class => "/**\n     * MySubclass::\$MyDocumentedProperty\n     *\n     * @var mixed\n     */",
            MyClass::class => [
                MyClass::class => "/**\n     * MyClass::\$MyDocumentedProperty\n     *\n     * @var mixed\n     */",
                MyTrait::class => "/**\n     * MyTrait::\$MyDocumentedProperty\n     *\n     * @var mixed\n     */",
                MyBaseTrait::class => "/**\n     * MyBaseTrait::\$MyDocumentedProperty\n     *\n     * @var mixed\n     */",
                MyReusedTrait::class => "/**\n     * MyReusedTrait::\$MyDocumentedProperty\n     *\n     * @var mixed\n     */",
            ],
            MyBaseClass::class => "/**\n     * MyBaseClass::\$MyDocumentedProperty\n     *\n     * @var mixed\n     */",
        ];

        return [
            [
                $expected1,
                null,
                new ReflectionProperty(MySubclass::class, 'MyDocumentedProperty'),
            ],
            [
                $expected1,
                $expected1c,
                new ReflectionProperty(MySubclass::class, 'MyDocumentedProperty'),
            ],
            [
                $expected2,
                null,
                new ReflectionProperty(MySubclass::class, 'MyDocumentedProperty'),
                null,
                true,
            ],
            [
                $expected2,
                $expected1c,
                new ReflectionProperty(MySubclass::class, 'MyDocumentedProperty'),
                null,
                true,
            ],
            [
                [
                    MyClass::class => "/**\n     * MyClass::\$MyPrivateProperty2\n     *\n     * @var mixed\n     */",
                    MyTrait::class => "/**\n     * MyTrait::\$MyPrivateProperty2\n     *\n     * @var mixed\n     */",
                ],
                null,
                new ReflectionProperty(MySubclass::class, 'MyPrivateProperty2'),
            ],
            [
                [
                    MySubclass::class => null,
                    MyUndocumentedClass::class => null,
                    MyClass::class => "/**\n     * MyClass::\$MyPrivateProperty2\n     *\n     * @var mixed\n     */",
                    MyTrait::class => "/**\n     * MyTrait::\$MyPrivateProperty2\n     *\n     * @var mixed\n     */",
                ],
                null,
                new ReflectionProperty(MySubclass::class, 'MyPrivateProperty2'),
                new ReflectionClass(MySubclass::class),
            ],
            [
                [
                    MyClass::class => [
                        MyClass::class => "/**\n     * MyClass::\$MyPrivateProperty2\n     *\n     * @var mixed\n     */",
                        MyTrait::class => "/**\n     * MyTrait::\$MyPrivateProperty2\n     *\n     * @var mixed\n     */",
                    ],
                ],
                null,
                new ReflectionProperty(MySubclass::class, 'MyPrivateProperty2'),
                null,
                true,
            ],
            [
                [
                    MySubclass::class => null,
                    MyUndocumentedClass::class => null,
                    MyClass::class => [
                        MyClass::class => "/**\n     * MyClass::\$MyPrivateProperty2\n     *\n     * @var mixed\n     */",
                        MyTrait::class => "/**\n     * MyTrait::\$MyPrivateProperty2\n     *\n     * @var mixed\n     */",
                    ],
                ],
                null,
                new ReflectionProperty(MySubclass::class, 'MyPrivateProperty2'),
                new ReflectionClass(MySubclass::class),
                true,
            ],
            [
                [
                    MyBaseClass::class => "/**\n     * MyBaseClass::\$MyPrivateProperty2\n     *\n     * @var mixed\n     */",
                ],
                null,
                new ReflectionProperty(MyBaseClass::class, 'MyPrivateProperty2'),
            ],
            [
                [
                    MyTraitAdaptationClass::class => [
                        MyBaseTrait::class => "/**\n     * MyBaseTrait::\$MyDocumentedProperty\n     *\n     * @var mixed\n     */",
                        MyReusedTrait::class => "/**\n     * MyReusedTrait::\$MyDocumentedProperty\n     *\n     * @var mixed\n     */",
                    ],
                ],
                null,
                new ReflectionProperty(MyTraitAdaptationClass::class, 'MyDocumentedProperty'),
                null,
                true,
            ],
        ];
    }

    /**
     * @dataProvider getAllConstantDocCommentsProvider
     *
     * @param array<class-string,array<class-string,string|null>|string|null> $expected
     * @param array<class-string,string|null>|null $expectedClassDocComments
     * @param ReflectionClass<*>|null $fromClass
     */
    public function testGetAllConstantDocComments(
        array $expected,
        ?array $expectedClassDocComments,
        ReflectionClassConstant $constant,
        ?ReflectionClass $fromClass = null,
        bool $groupTraits = false
    ): void {
        if ($expectedClassDocComments === null) {
            $actual = PHPDocUtil::getAllConstantDocComments($constant, $fromClass, $groupTraits);
            $this->assertSame($expected, $actual, $this->getMessage($actual));
        } else {
            $actual = PHPDocUtil::getAllConstantDocComments($constant, $fromClass, $groupTraits, $classDocComments);
            $this->assertSame($expected, $actual, $this->getMessage($actual));
            $this->assertSame($expectedClassDocComments, $classDocComments, $this->getMessage($classDocComments, '$expectedClassDocComments'));
        }
    }

    /**
     * @return iterable<array{array<class-string,array<class-string,string|null>|string|null>,array<class-string,string|null>|null,ReflectionClassConstant,3?:ReflectionClass<*>|null,4?:bool}>
     */
    public static function getAllConstantDocCommentsProvider(): iterable
    {
        $expected1 = [
            MyClass::class => "/**\n     * MyClass::MY_CONSTANT\n     */",
            MyBaseClass::class => "/**\n     * MyBaseClass::MY_CONSTANT\n     */",
        ];

        $expected1c = [
            MyClass::class => "/**\n * MyClass\n */",
            MyBaseClass::class => "/**\n * MyBaseClass\n */",
        ];

        $expected2 = [
            MyClass::class => "/**\n     * MyClass::MY_CONSTANT\n     */",
            MyBaseClass::class => "/**\n     * MyBaseClass::MY_CONSTANT\n     */",
        ];

        $expected3 = [
            MyClassWithTraitWithConstants::class => null,
            MyTraitWithConstants::class => "/**\n     * MyTraitWithConstants::MY_CONSTANT\n     */",
            MyClass::class => "/**\n     * MyClass::MY_CONSTANT\n     */",
            MyBaseClass::class => "/**\n     * MyBaseClass::MY_CONSTANT\n     */",
        ];

        $expected4 = [
            MyTraitWithConstants::class => "/**\n     * MyTraitWithConstants::MY_TRAIT_CONSTANT\n     */",
        ];

        $expected4c = [
            MyTraitWithConstants::class => "/**\n * MyTraitWithConstants\n */",
        ];

        $expected5 = [
            MyClassWithTraitWithConstants::class => [
                MyTraitWithConstants::class => "/**\n     * MyTraitWithConstants::MY_TRAIT_CONSTANT\n     */",
            ],
        ];

        yield from [
            [
                $expected1,
                null,
                new ReflectionClassConstant(MyClass::class, 'MY_CONSTANT'),
            ],
            [
                $expected1,
                $expected1c,
                new ReflectionClassConstant(MyClass::class, 'MY_CONSTANT'),
            ],
            [
                $expected2,
                null,
                new ReflectionClassConstant(MyClass::class, 'MY_CONSTANT'),
                null,
                true,
            ],
            [
                $expected2,
                $expected1c,
                new ReflectionClassConstant(MyClass::class, 'MY_CONSTANT'),
                null,
                true,
            ],
        ];

        if (\PHP_VERSION_ID >= 80200) {
            yield from [
                [
                    $expected1,
                    null,
                    new ReflectionClassConstant(MyClassWithTraitWithConstants::class, 'MY_CONSTANT'),
                ],
                [
                    $expected1,
                    $expected1c,
                    new ReflectionClassConstant(MyClassWithTraitWithConstants::class, 'MY_CONSTANT'),
                ],
                [
                    $expected3,
                    null,
                    new ReflectionClassConstant(MyClassWithTraitWithConstants::class, 'MY_CONSTANT'),
                    new ReflectionClass(MyClassWithTraitWithConstants::class),
                ],
                [
                    $expected3,
                    [
                        MyClassWithTraitWithConstants::class => "/**\n * MyClassWithTraitWithConstants\n */",
                        MyTraitWithConstants::class => "/**\n * MyTraitWithConstants\n */",
                        MyClass::class => "/**\n * MyClass\n */",
                        MyBaseClass::class => "/**\n * MyBaseClass\n */",
                    ],
                    new ReflectionClassConstant(MyClassWithTraitWithConstants::class, 'MY_CONSTANT'),
                    new ReflectionClass(MyClassWithTraitWithConstants::class),
                ],
                [
                    $expected4,
                    null,
                    new ReflectionClassConstant(MyClassWithTraitWithConstants::class, 'MY_TRAIT_CONSTANT'),
                ],
                [
                    $expected4,
                    $expected4c,
                    new ReflectionClassConstant(MyClassWithTraitWithConstants::class, 'MY_TRAIT_CONSTANT'),
                ],
                [
                    $expected5,
                    null,
                    new ReflectionClassConstant(MyClassWithTraitWithConstants::class, 'MY_TRAIT_CONSTANT'),
                    null,
                    true,
                ],
                [
                    $expected5,
                    $expected4c,
                    new ReflectionClassConstant(MyClassWithTraitWithConstants::class, 'MY_TRAIT_CONSTANT'),
                    null,
                    true,
                ],
            ];
        }
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
     * @return iterable<array{string[],string,string,3?:bool}>
     */
    public static function getTypeDeclarationProvider(): iterable
    {
        yield from [
            [
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
            [
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
            yield [
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
            yield [
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
     * @return iterable<array{string[],string,string}>
     */
    public static function getParameterDeclarationProvider(): iterable
    {
        yield [
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
            yield [
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
            yield [
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

    /**
     * @param mixed $actual
     */
    private static function getMessage($actual, string $expectedName = '$expected'): string
    {
        /** @var array<class-string,non-empty-string>|null */
        static $constants;

        if ($constants === null) {
            /** @var array<string,class-string> */
            $aliases = Arr::pluck(TokenExtractor::fromFile(__FILE__)->getImports(), '1', true);
            $constants = array_map(
                fn(string $alias) => $alias . '::class',
                array_flip($aliases),
            );
        }

        return sprintf(
            'If classes changed, replace %s with: %s',
            $expectedName,
            Get::code($actual, ', ', ' => ', null, '    ', [], $constants),
        );
    }
}
