<?php declare(strict_types=1);

namespace Salient\Tests\PHPDoc;

use Salient\PHPDoc\Tag\ErrorTag;
use Salient\PHPDoc\Tag\GenericTag;
use Salient\PHPDoc\Tag\MethodParam;
use Salient\PHPDoc\Tag\MethodTag;
use Salient\PHPDoc\Tag\ParamTag;
use Salient\PHPDoc\Tag\PropertyTag;
use Salient\PHPDoc\Tag\ReturnTag;
use Salient\PHPDoc\Tag\TemplateTag;
use Salient\PHPDoc\Tag\VarTag;
use Salient\PHPDoc\PHPDoc;
use Salient\PHPDoc\PHPDocRegex;
use Salient\Tests\Reflection\MyBaseClass;
use Salient\Tests\Reflection\MyClass;
use Salient\Tests\TestCase;
use Salient\Utility\Arr;
use Salient\Utility\Regex;
use Salient\Utility\Str;
use InvalidArgumentException;
use stdClass;

/**
 * @covers \Salient\PHPDoc\PHPDoc
 * @covers \Salient\PHPDoc\PHPDocUtil
 * @covers \Salient\PHPDoc\Tag\AbstractTag
 * @covers \Salient\PHPDoc\Tag\ErrorTag
 * @covers \Salient\PHPDoc\Tag\MethodParam
 * @covers \Salient\PHPDoc\Tag\MethodTag
 * @covers \Salient\PHPDoc\Tag\ParamTag
 * @covers \Salient\PHPDoc\Tag\PropertyTag
 * @covers \Salient\PHPDoc\Tag\ReturnTag
 * @covers \Salient\PHPDoc\Tag\TemplateTag
 * @covers \Salient\PHPDoc\Tag\VarTag
 */
final class PHPDocTest extends TestCase
{
    /**
     * @dataProvider invalidDocBlockProvider
     */
    public function testInvalidDocBlock(string $docBlock): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid DocBlock');
        new PHPDoc($docBlock);
    }

    /**
     * @return array<array{string}>
     */
    public static function invalidDocBlockProvider(): array
    {
        return [
            ['/***/'],
            [<<<'EOF'
/**
 *

 */
EOF],
        ];
    }

    public function testUnterminatedCodeFence(): void
    {
        $docBlock = <<<'EOF'
/**
 * Summary
 *
 * ```php
 * $this->doSomething();
 */
EOF;

        $phpDoc = new PHPDoc($docBlock);
        $this->assertSame('Summary', $phpDoc->getSummary());
        $this->assertSame("```php\n\$this->doSomething();\n```", $phpDoc->getDescription());
    }

    /**
     * @dataProvider docBlockWithNoSummaryProvider
     */
    public function testDocBlockWithNoSummary(string $docBlock): void
    {
        $phpDoc = new PHPDoc($docBlock);
        $this->assertNull($phpDoc->getSummary());
        $this->assertNull($phpDoc->getDescription());
    }

    /**
     * @return array<array{string}>
     */
    public static function docBlockWithNoSummaryProvider(): array
    {
        return [
            ['/** */'],
            [<<<'EOF'
/**
 */
EOF],
            [<<<'EOF'
/**
 *
 */
EOF],
            [<<<'EOF'
/**
 * @internal
 */
EOF],
        ];
    }

    /**
     * @dataProvider docBlockWithNoDescriptionProvider
     */
    public function testDocBlockWithNoDescription(string $docBlock): void
    {
        $phpDoc = new PHPDoc($docBlock);
        $this->assertSame('Summary', $phpDoc->getSummary());
        $this->assertNull($phpDoc->getDescription());
    }

    /**
     * @return array<array{string}>
     */
    public static function docBlockWithNoDescriptionProvider(): array
    {
        return [
            [<<<'EOF'
/**
 * Summary
 */
EOF],
            [<<<'EOF'
/**
 * Summary
 * @internal
 */
EOF],
            [<<<'EOF'
/**
 * Summary
 *
 *
 *
 * @internal
 */
EOF],
        ];
    }

    public function testDocBlockWithLeadingAsterisk(): void
    {
        $phpDoc = new PHPDoc('/** * Summary */');
        $this->assertSame('* Summary', $phpDoc->getSummary());
    }

    public function testFromTags(): void
    {
        $phpDoc = PHPDoc::fromTags([
            new GenericTag('api'),
            new ParamTag('id', 'int|string', false, true, 'Foo'),
            new ReturnTag('static', 'Bar'),
            $var = new VarTag('int', null, 'Foo', MyBaseClass::class),
            new VarTag('string', 'Baz', 'Description of Baz'),
            new MethodTag('run', 'int', [], false, 'Perform the operation'),
            new PropertyTag('LastResult', 'int', true, false, 'Result of last operation'),
            new TemplateTag('TObject', 'object', stdClass::class, false, false, MyClass::class),
            new TemplateTag('T', null, null, false, false, MyBaseClass::class),
        ], 'Summary', 'Description', MyClass::class);

        $docBlock = <<<'EOF'
/**
 * Summary
 *
 * Description
 *
 * @template TObject of object = stdClass
 *
 * @api
 * @param int|string ...$id Foo
 * @return static Bar
 * @var int Foo
 * @var string $Baz Description of Baz
 * @method int run() Perform the operation
 * @property-read int $LastResult Result of last operation
 */
EOF;

        $this->assertSame(Str::eolFromNative($docBlock), (string) $phpDoc);
        $this->assertSame(['T' => '@template T'], Arr::toStrings($phpDoc->getTemplatesForTag($var)));

        $this->assertSame('/** */', (string) PHPDoc::fromTags([]));
        $this->assertSame("/**\n * Summary\n */", (string) PHPDoc::fromTags([], 'Summary'));
        $this->assertSame("/**\n * @var int Foo\n */", (string) PHPDoc::fromTags([$var]));
        $this->assertSame("/**\n * Summary\n *\n * @var int Foo\n */", (string) PHPDoc::fromTags([$var], 'Summary'));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('$description must be empty if $summary is empty');
        PHPDoc::fromTags([], '', 'Description');
    }

    public function testFromDocBlocks(): void
    {
        $docBlocks = [
            <<<'EOF'
/**
 * @param $arg1 Description from ClassC (untyped)
 * @param string[] $arg3
 * @param bool &$arg4
 * @param mixed ...$arg5
 * @return $this Description from ClassC
 */
EOF,
            <<<'EOF'
/**
 * Summary from ClassB
 *
 * {@inheritDoc}
 *
 * More information from ClassB
 *
 *
 * @param int|string $arg1
 * @param array $arg3 Description from ClassB
 * @return $this
 */
EOF,
            <<<'EOF'
/**
 * Summary from ClassA
 *
 * Description from ClassA
 *
 * ```php
 * // code here
 * ```
 *
 * @param mixed $arg1 Description from ClassA
 * @param string $arg2 Description from ClassA
 * @param array $arg3 Description from ClassA
 * @return $this
 */
EOF,
        ];

        $merged = <<<'EOF'
/**
 * Summary from ClassB
 *
 * Description from ClassA
 *
 * ```php
 * // code here
 * ```
 *
 * More information from ClassB
 *
 * @param int|string $arg1 Description from ClassC (untyped)
 * @param string[] $arg3 Description from ClassB
 * @param bool &$arg4
 * @param mixed ...$arg5
 * @param string $arg2 Description from ClassA
 * @return $this Description from ClassC
 */
EOF;

        $phpDoc = PHPDoc::fromDocBlocks($docBlocks);
        $original = $phpDoc->getOriginal();

        $this->assertSame(Str::eolFromNative($docBlocks[0]), (string) $original);
        $this->assertSame(Str::eolFromNative($merged), (string) $phpDoc);
        $this->assertSame('Summary from ClassB', $phpDoc->getSummary());
        $this->assertSame("Description from ClassA\n\n```php\n// code here\n```\n\nMore information from ClassB", $phpDoc->getDescription());
        $this->assertSame([
            'param' => [
                '@param int|string $arg1 Description from ClassC (untyped)',
                '@param string[] $arg3 Description from ClassB',
                '@param bool &$arg4',
                '@param mixed ...$arg5',
                '@param string $arg2 Description from ClassA',
            ],
            'return' => [
                '@return $this Description from ClassC',
            ],
        ], array_map([Arr::class, 'toStrings'], $phpDoc->getTags()));

        $params = $phpDoc->getParams();
        $this->assertSame(['arg1', 'arg3', 'arg4', 'arg5', 'arg2'], array_keys($params));

        $tag = $params['arg1'];
        $this->assertSame('arg1', $tag->getName());
        $this->assertSame('int|string', $tag->getType());
        $this->assertSame('Description from ClassC (untyped)', $tag->getDescription());
        $this->assertFalse($tag->isPassedByReference());
        $this->assertFalse($tag->isVariadic());

        $tag = $params['arg3'];
        $this->assertSame('arg3', $tag->getName());
        $this->assertSame('string[]', $tag->getType());
        $this->assertSame('Description from ClassB', $tag->getDescription());
        $this->assertFalse($tag->isPassedByReference());
        $this->assertFalse($tag->isVariadic());

        $tag = $params['arg4'];
        $this->assertSame('arg4', $tag->getName());
        $this->assertSame('bool', $tag->getType());
        $this->assertNull($tag->getDescription());
        $this->assertTrue($tag->isPassedByReference());
        $this->assertFalse($tag->isVariadic());

        $tag = $params['arg5'];
        $this->assertSame('arg5', $tag->getName());
        $this->assertSame('mixed', $tag->getType());
        $this->assertNull($tag->getDescription());
        $this->assertFalse($tag->isPassedByReference());
        $this->assertTrue($tag->isVariadic());

        $tag = $params['arg2'];
        $this->assertSame('arg2', $tag->getName());
        $this->assertSame('string', $tag->getType());
        $this->assertSame('Description from ClassA', $tag->getDescription());
        $this->assertFalse($tag->isPassedByReference());
        $this->assertFalse($tag->isVariadic());

        $this->assertNotNull($tag = $phpDoc->getReturn());
        $this->assertSame('$this', $tag->getType());
        $this->assertSame('Description from ClassC', $tag->getDescription());
        $this->assertSame('@return $this Description from ClassC', (string) $tag);
    }

    /**
     * @dataProvider phpDocProvider
     *
     * @param PHPDoc|string|null $classDocBlock
     * @param class-string|null $class
     */
    public function testPHPDoc(
        PHPDoc $expected,
        ?string $docBlock = null,
        $classDocBlock = null,
        ?string $class = null,
        ?string $member = null,
        bool $normalise = false,
        ?string $string = null
    ): void {
        $phpDoc = new PHPDoc($docBlock, $classDocBlock, $class, $member);
        if ($normalise) {
            $phpDoc = $phpDoc->normalise()->flatten();
        }
        $this->assertEquals($expected, $phpDoc);
        $this->assertSame(Str::eolFromNative($string ?? $docBlock ?? ''), (string) $phpDoc);
    }

    /**
     * @return array<array{PHPDoc,1?:string|null,2?:PHPDoc|string|null,3?:class-string|null,4?:string|null,5?:bool,6?:string|null}>
     */
    public static function phpDocProvider(): array
    {
        return [
            [
                PHPDoc::fromTags([
                    new MethodTag('getString', 'string', [], false, 'Description of getString()'),
                    new MethodTag('setInteger', 'void', [
                        'integer' => new MethodParam('integer', 'int'),
                    ], false, 'Description of setInteger()'),
                    new MethodTag('setString', null, [
                        'string' => new MethodParam('string', 'string'),
                    ]),
                    new MethodTag('setBoth', '$this', [
                        'integer' => new MethodParam('integer', 'int', '-1'),
                        'string' => new MethodParam('string', 'string', "''"),
                    ]),
                    new MethodTag('setAny', 'static', [
                        'integerOrString' => new MethodParam('integerOrString', 'int|string', null, true),
                    ], false, 'Description of setAny()'),
                    new MethodTag('staticGetter', 'int|string', [], true, 'Description of staticGetter()'),
                    new MethodTag('staticFactory', 'static[]', [
                        'id' => new MethodParam('id', null, null, true),
                    ], true, 'Description of staticFactory()'),
                    new MethodTag('getChildren', 'static[]', [], false, 'Description of getChildren()'),
                ]),
                <<<'EOF'
/**
 * @method string getString() Description of getString()
 * @method void setInteger(int $integer) Description of setInteger()
 * @method setString(string $string)
 * @method $this setBoth(int $integer = -1, string $string = '')
 * @method static setAny(int|string ...$integerOrString) Description of setAny()
 * @method static int|string staticGetter() Description of staticGetter()
 * @method static static[] staticFactory(...$id) Description of staticFactory()
 * @method static[] getChildren() Description of getChildren()
 */
EOF,
            ],
            [
                PHPDoc::fromTags([
                    new PropertyTag('FullName', 'string', true, false),
                    new PropertyTag('Children', 'static[]', false, false, 'Description of $Children'),
                    new PropertyTag('Writable', null, false, true),
                ]),
                <<<'EOF'
/**
 * @property-read string $FullName
 * @property static[] $Children Description of $Children
 * @property-write $Writable
 */
EOF,
            ],
            [
                PHPDoc::fromTags([
                    new VarTag('string', 'Name', 'Description of $Name', MyClass::class, '$Name'),
                ], null, null, MyClass::class, '$Name'),
                <<<'EOF'
/**
 * @var string $Name Description of $Name
 */
EOF,
                null,
                MyClass::class,
                '$Name',
            ],
            [
                PHPDoc::fromTags([
                    new VarTag('string', null, null, MyClass::class, '$Name'),
                ], 'Description of $Name', null, MyClass::class, '$Name'),
                <<<'EOF'
/**
 * @var string $Name Description of $Name
 */
EOF,
                null,
                MyClass::class,
                '$Name',
                true,
                <<<'EOF'
/**
 * Description of $Name
 *
 * @var string
 */
EOF,
            ],
            [
                PHPDoc::fromTags([
                    new ErrorTag('param', 'Invalid syntax for @param in DocBlock'),
                ]),
                '/** @param */',
                null,
                null,
                null,
                false,
                '/** */',
            ],
            [
                PHPDoc::fromTags([
                    new ErrorTag('param', 'Invalid syntax for @param in DocBlock of ' . MyClass::class, null, MyClass::class),
                ], null, null, MyClass::class),
                '/** @param */',
                null,
                MyClass::class,
                null,
                false,
                '/** */',
            ],
            [
                PHPDoc::fromTags([
                    new ErrorTag('param', 'Invalid syntax for @param in DocBlock of ' . MyClass::class . '::bar()', null, MyClass::class, 'bar()'),
                ], null, null, MyClass::class, 'bar()'),
                '/** @param */',
                null,
                MyClass::class,
                'bar()',
                false,
                '/** */',
            ],
            [
                PHPDoc::fromTags([
                    new ErrorTag('param', 'Invalid syntax for @param in DocBlock', 'bool notAVariable'),
                    new ErrorTag('return', 'Invalid syntax for @return in DocBlock'),
                    new ErrorTag('return', 'Invalid syntax for @return in DocBlock', '/notAType'),
                    new ErrorTag('var', 'Invalid syntax for @var in DocBlock'),
                    new ErrorTag('var', 'Invalid syntax for @var in DocBlock', '$variable'),
                    new ErrorTag('template', 'Invalid syntax for @template in DocBlock'),
                ]),
                <<<'EOF'
/**
 * @param bool notAVariable
 * @return
 * @return /notAType
 * @var
 * @var $variable
 * @template
 */
EOF,
                null,
                null,
                null,
                false,
                '/** */',
            ],
        ];
    }

    /**
     * @dataProvider paramTagsProvider
     *
     * @param string[] $names
     * @param array<string|null> $types
     * @param array<string|null> $descriptions
     * @param bool[] $isReferenceValues
     * @param bool[] $isVariadicValues
     * @param string[] $strings
     */
    public function testParamTags(
        string $expected,
        string $docBlock,
        array $names,
        array $types,
        array $descriptions,
        array $isReferenceValues,
        array $isVariadicValues,
        array $strings
    ): void {
        $phpDoc = new PHPDoc($docBlock);
        $this->assertSame($expected, (string) $phpDoc);
        $params = $phpDoc->getParams();
        $this->assertSame($names, array_keys($params));
        foreach ($names as $i => $name) {
            $this->assertSame($name, ($param = $params[$name])->getName());
            $this->assertSame($types[$i], $param->getType());
            $this->assertSame($descriptions[$i], $param->getDescription());
            $this->assertSame($isReferenceValues[$i], $param->isPassedByReference());
            $this->assertSame($isVariadicValues[$i], $param->isVariadic());
            $this->assertSame($strings[$i], (string) $param);
        }
    }

    /**
     * @return iterable<array{string,string,string[],array<string|null>,array<string|null>,bool[],bool[],string[]}>
     */
    public static function paramTagsProvider(): iterable
    {
        foreach ([
            [
                <<<'EOF'
/**
 * @param (int|string)[] &...$idListsByReference
 */
EOF,
                null,
            ],
            [
                <<<'EOF'
/**
 * @param (int|string)[] & ... $idListsByReference
 */
EOF,
                null,
            ],
            [
                <<<'EOF'
/**
 * @param (int|string)[] &...$idListsByReference Description of $idListsByReference
 */
EOF,
                'Description of $idListsByReference',
            ],
            [
                <<<'EOF'
/**
 * @param (int|string)[] &...$idListsByReference
 * Description of $idListsByReference
 */
EOF,
                'Description of $idListsByReference',
            ],
        ] as [$docBlock, $description]) {
            $tag = Arr::implode(' ', ['@param (int|string)[] &...$idListsByReference', $description], '');
            yield [
                "/**\n * $tag\n */",
                $docBlock,
                ['idListsByReference'],
                ['(int|string)[]'],
                [$description],
                [true],
                [true],
                [$tag],
            ];
        }
    }

    /**
     * @dataProvider varTagsProvider
     *
     * @param array<int|string> $keys
     * @param array<string|null> $names
     * @param array<string|null> $types
     * @param array<string|null> $descriptions
     * @param string[] $strings
     */
    public function testVarTags(
        string $docBlock,
        ?string $summary,
        ?string $description,
        array $keys,
        array $names,
        array $types,
        array $descriptions,
        array $strings,
        bool $normalise = true
    ): void {
        $phpDoc = new PHPDoc($docBlock);
        if ($normalise) {
            $phpDoc = $phpDoc->normalise();
        }
        $this->assertSame($summary, $phpDoc->getSummary());
        $this->assertSame(
            $description,
            $phpDoc->getDescription() === null
                ? null
                : Str::eolToNative($phpDoc->getDescription()),
        );
        $vars = $phpDoc->getVars();
        $this->assertCount(count($keys), $vars);
        foreach ($keys as $i => $key) {
            $this->assertArrayHasKey($key, $vars);
            $this->assertSame($names[$i], ($var = $vars[$key])->getName());
            $this->assertSame($types[$i], $var->getType());
            $this->assertSame($descriptions[$i], $var->getDescription());
            $this->assertSame($strings[$i], (string) $var);
        }
    }

    /**
     * @return array<array{string,string|null,string|null,array<int|string>,array<string|null>,array<string|null>,array<string|null>,string[],8?:bool}>
     */
    public static function varTagsProvider(): array
    {
        return [
            [
                <<<'EOF'
/** @var int This is a counter. */
EOF,
                'This is a counter.',
                null,
                [0],
                [null],
                ['int'],
                [null],
                ['@var int'],
            ],
            [
                <<<'EOF'
/**
 * @var int
 * This is a counter.
 */
EOF,
                null,
                null,
                [0],
                [null],
                ['int'],
                ['This is a counter.'],
                ['@var int This is a counter.'],
                false,
            ],
            [
                <<<'EOF'
/**
 * This is a counter.
 *
 * @var int This is a counter.
 */
EOF,
                'This is a counter.',
                null,
                [0],
                [null],
                ['int'],
                [null],
                ['@var int'],
            ],
            [
                <<<'EOF'
/** @var int $counter This is a counter. */
EOF,
                null,
                null,
                ['counter'],
                ['counter'],
                ['int'],
                ['This is a counter.'],
                ['@var int $counter This is a counter.'],
            ],
            [
                <<<'EOF'
/**
 * @var int $counter
 * This is a counter.
 */
EOF,
                null,
                null,
                ['counter'],
                ['counter'],
                ['int'],
                ['This is a counter.'],
                ['@var int $counter This is a counter.'],
            ],
            [
                <<<'EOF'
/**
 * @var int This is a counter.
 * @var string|null This is a nullable string.
 */
EOF,
                null,
                null,
                [0, 1],
                [null, null],
                ['int', 'string|null'],
                ['This is a counter.', 'This is a nullable string.'],
                ['@var int This is a counter.', '@var string|null This is a nullable string.'],
            ],
            [
                <<<'EOF'
/**
 * Full docblock with a summary.
 *
 * And a description.
 *
 * @var int And a variable description.
 */
EOF,
                'Full docblock with a summary.',
                <<<'EOF'
And a description.

And a variable description.
EOF,
                [0],
                [null],
                ['int'],
                [null],
                ['@var int'],
            ],
            [
                <<<'EOF'
/**
 * Full docblock with a summary.
 *
 * @var int And a variable description.
 */
EOF,
                'Full docblock with a summary.',
                'And a variable description.',
                [0],
                [null],
                ['int'],
                [null],
                ['@var int'],
            ],
            [
                <<<'EOF'
/**
 * Full docblock with a summary.
 *
 * @var int
 */
EOF,
                'Full docblock with a summary.',
                null,
                [0],
                [null],
                ['int'],
                [null],
                ['@var int'],
            ],
            [
                <<<'EOF'
/**
 * @var string $name        Should contain a description of $name
 * @var string $description Should contain a description of $description
 */
EOF,
                null,
                null,
                ['name', 'description'],
                ['name', 'description'],
                ['string', 'string'],
                ['Should contain a description of $name', 'Should contain a description of $description'],
                ['@var string $name Should contain a description of $name', '@var string $description Should contain a description of $description'],
            ],
            [
                <<<'EOF'
/** @var int */
EOF,
                null,
                null,
                [0],
                [null],
                ['int'],
                [null],
                ['@var int'],
            ],
        ];
    }

    public function testInvalidPropertyTag(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('$isReadOnly and $isWriteOnly cannot both be true');
        new PropertyTag('foo', null, true, true);
    }

    public function testTemplateTags(): void
    {
        $docBlock = <<<'EOF'
/**
 * Summary
 *
 * @template-covariant T0 foo
 * @template T1 of int
 * @template T2 = true
 * @template T3 of array|null = array{}
 * @param class-string<T0> $id
 * @return T0
 */
EOF;
        $phpDoc = new PHPDoc($docBlock);
        $this->assertSame('Summary', $phpDoc->getSummary());
        $this->assertNull($phpDoc->getDescription());
        $this->assertCount(4, $templates = $phpDoc->getTemplates());
        $this->assertNull($templates['T0']->getType());
        $this->assertSame('int', $templates['T1']->getType());
        $this->assertNull($templates['T2']->getType());
        $this->assertSame('array|null', $templates['T3']->getType());
        $this->assertNull($templates['T0']->getDefault());
        $this->assertNull($templates['T1']->getDefault());
        $this->assertSame('true', $templates['T2']->getDefault());
        $this->assertSame('array{}', $templates['T3']->getDefault());
        $this->assertSame('@template-covariant T0', (string) $templates['T0']);
        $this->assertSame('@template T1 of int', (string) $templates['T1']);
        $this->assertSame('@template T2 = true', (string) $templates['T2']);
        $this->assertSame('@template T3 of array|null = array{}', (string) $templates['T3']);

        $template = $templates['T0'];
        $this->assertNull($template->getDescription());
        $this->assertTrue($template->isCovariant());
        $this->assertFalse($template->isContravariant());
        $this->assertNotSame($template, $template2 = $template->withName('TInvariant'));
        $this->assertNotSame($template2, $template3 = $template2->withType('object|null'));
        $this->assertSame($template3, $template3->withDescription(null));
        $this->assertNotSame($template3, $template4 = $template3->withDefault('null'));
        $this->assertNotSame($template4, $template5 = $template4->withoutVariance());
        $this->assertSame('@template TInvariant of object|null = null', (string) $template5);
        $this->assertNotSame($template5, $template6 = $template5->withType(null));
        $this->assertSame('@template TInvariant = null', (string) $template6);
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid description for @template in DocBlock');
        $template6->withDescription('foo');
    }

    public function testTemplateInheritance(): void
    {
        $docBlock = <<<'EOF'
/**
 * Summary
 *
 * @template T
 * @template TArray as array|null = array{}
 * @param class-string<T> $id
 * @param TArray $array
 * @param TKey $key
 * @param TValue $value
 * @return T
 */
EOF;
        $classDocBlock = <<<'EOF'
/**
 * Class summary
 *
 * @template T of string = ""
 * @template TKey of array-key = int
 * @template TValue of object
 */
EOF;
        $classPhpDoc = new PHPDoc($classDocBlock, null, MyClass::class);
        $phpDoc = new PHPDoc($docBlock, $classPhpDoc, MyClass::class, 'myMethod()');
        $this->assertSame(MyClass::class, $classPhpDoc->getClass());
        $this->assertSame(MyClass::class, $phpDoc->getClass());
        $this->assertNull($classPhpDoc->getMember());
        $this->assertSame('myMethod()', $phpDoc->getMember());
        $this->assertSame('Summary', $phpDoc->getSummary());
        $this->assertNull($phpDoc->getDescription());
        $this->assertCount(3, $classTemplates = $classPhpDoc->getTemplates());
        $this->assertCount(2, $standaloneTemplates = $phpDoc->getTemplates(false));
        $this->assertSame(['T', 'TArray'], array_keys($standaloneTemplates));
        $this->assertCount(4, $templates = $phpDoc->getTemplates());
        $this->assertSame('T', $classTemplates['T']->getName());
        $this->assertSame('T', $templates['T']->getName());
        $this->assertSame('TArray', $templates['TArray']->getName());
        $this->assertSame('TKey', $templates['TKey']->getName());
        $this->assertSame('TValue', $templates['TValue']->getName());
        $this->assertSame('string', $classTemplates['T']->getType());
        $this->assertNull($templates['T']->getType());
        $this->assertSame('array|null', $templates['TArray']->getType());
        $this->assertSame('array-key', $templates['TKey']->getType());
        $this->assertSame('object', $templates['TValue']->getType());
        $this->assertSame('""', $classTemplates['T']->getDefault());
        $this->assertNull($templates['T']->getDefault());
        $this->assertSame('array{}', $templates['TArray']->getDefault());
        $this->assertSame('int', $templates['TKey']->getDefault());
        $this->assertNull($templates['TValue']->getDefault());
        $this->assertSame('@template T of string = ""', (string) $classTemplates['T']);
        $this->assertSame('@template T', (string) $templates['T']);
        $this->assertSame('@template TArray of array|null = array{}', (string) $templates['TArray']);
        $this->assertSame('@template TKey of array-key = int', (string) $templates['TKey']);
        $this->assertSame('@template TValue of object', (string) $templates['TValue']);
    }

    public function testFences(): void
    {
        $docBlock =
            <<<'EOF'
/**
 * Summary
 *
 * Description with multiple code blocks:
 *
 * ```php
 * $this->doSomething();
 * ```
 *
 * Three, to be precise (including within the `@var`):
 *
 * ```
 * @var Not this `@var`, though. It's in a fence.
 * ```
 *
 * @var callable|null Something like this:
 * ```php
 * callback(string $value): string
 * ```
 */
EOF;

        $phpDoc = new PHPDoc($docBlock);

        $this->assertSame('Summary', $phpDoc->getSummary());
        $this->assertSame($description = <<<'EOF'
Description with multiple code blocks:

```php
$this->doSomething();
```

Three, to be precise (including within the `@var`):

```
@var Not this `@var`, though. It's in a fence.
```
EOF, Str::eolToNative((string) $phpDoc->getDescription()));

        $phpDoc = $phpDoc->normalise();

        $this->assertSame($description . <<<'EOF'


Something like this:
```php
callback(string $value): string
```
EOF, Str::eolToNative((string) $phpDoc->getDescription()));

        $this->assertCount(1, $vars = $phpDoc->getVars());
        $this->assertNull($vars[0]->getName());
        $this->assertSame('callable|null', $vars[0]->getType());
        $this->assertNull($vars[0]->getDescription());
    }

    public function testBlankLines(): void
    {
        $docBlock = <<<'EOF'
/**
 *
 * Summary
 *
 *
 * Parts are surrounded by superfluous blank lines.
 *
 *
 *     @internal
 *
 *
 *     @template T0 of object
 *
 *
 *     @template T1
 *
 *
 *     @var class-string<T0>|null $Class
 *
 *
 *     @param class-string<T0>|null $class
 *
 *
 *     @inheritDoc
 *
 *
 */
EOF;
        $phpDoc = new PHPDoc($docBlock);
        $this->assertSame('Summary', $phpDoc->getSummary());
        $this->assertSame('Parts are surrounded by superfluous blank lines.', $phpDoc->getDescription());
        $this->assertCount(2, $templates = $phpDoc->getTemplates());
        $this->assertSame('T0', $templates['T0']->getName());
        $this->assertSame('object', $templates['T0']->getType());
        $this->assertSame('T1', $templates['T1']->getName());
        $this->assertNull($templates['T1']->getType());
        $this->assertCount(1, $vars = $phpDoc->getVars());
        $this->assertSame('class-string<T0>|null', $vars['Class']->getType());
        $this->assertCount(1, $params = $phpDoc->getParams());
        $this->assertSame('class-string<T0>|null', $params['class']->getType());
    }

    /**
     * @dataProvider eolProvider
     */
    public function testEol(string $docBlock, string $summary, string $description): void
    {
        $phpDoc = new PHPDoc($docBlock);
        $this->assertSame($summary, $phpDoc->getSummary());
        $this->assertSame($description, $phpDoc->getDescription());
    }

    /**
     * @return array<string,string[]>
     */
    public static function eolProvider(): array
    {
        return [
            'CRLF' => [
                "/**\r\n * Summary \r\n *  \r\n * Has trailing spaces and CRLF end-of-lines. \r\n *  \r\n * @internal \r\n */",
                'Summary',
                'Has trailing spaces and CRLF end-of-lines.',
            ],
            'LF' => [
                "/**\n * Summary \n *  \n * Has trailing spaces and LF end-of-lines. \n *  \n * @internal \n */",
                'Summary',
                'Has trailing spaces and LF end-of-lines.',
            ],
            'CR' => [
                "/**\r * Summary \r *  \r * Has trailing spaces and CR end-of-lines. \r *  \r * @internal \r */",
                'Summary',
                'Has trailing spaces and CR end-of-lines.',
            ],
        ];
    }

    /**
     * @dataProvider typeRegexProvider
     */
    public function testTypeRegex(string $phpDocType, bool $expectMatch = true): void
    {
        $regex = Regex::delimit('^' . PHPDocRegex::PHPDOC_TYPE . '$', '/');
        if ($expectMatch) {
            $this->assertMatchesRegularExpression($regex, trim($phpDocType));
        } else {
            $this->assertDoesNotMatchRegularExpression($regex, trim($phpDocType));
        }
    }

    /**
     * @return iterable<string,array{string,1?:bool}>
     */
    public static function typeRegexProvider(): iterable
    {
        yield from [
            'array{ }' => ['array{ }'],
            'array{,}' => ['array{,}', false],
        ];

        // Extracted from tests/PHPStan/Parser/TypeParserTest.php in
        // phpstan/phpdoc-parser
        $data = [
            ['string'],
            ['  string  '],
            [' ( string ) '],
            ['( ( string ) )'],
            ['\Foo\Bar\Baz'],
            ['  \Foo\Bar\Baz  '],
            [' ( \Foo\Bar\Baz ) '],
            ['( ( \Foo\Bar\Baz ) )'],
            ['string|int'],
            ['string | int'],
            ['(string | int)'],
            ['string | int | float'],
            ['string&int'],
            ['string & int'],
            ['(string & int)'],
            ['(' . \PHP_EOL . '  string' . \PHP_EOL . '  &' . \PHP_EOL . '  int' . \PHP_EOL . ')'],
            ['string & int & float'],
            ['string & (int | float)'],
            ['string | (int & float)'],
            ['string & int | float'],
            ['string | int & float'],
            ['string[]'],
            ['string [  ] '],
            ['(string | int | float)[]'],
            ['string[][][]'],
            ['string [  ] [][]'],
            ['(((string | int | float)[])[])[]'],
            ['$this'],
            ['?int'],
            ['?Foo<Bar>'],
            ['array<int, Foo\Bar>'],
            ["array {'a': int}"],
            ['array{a: int}'],
            ['array{a: ?int}'],
            ['array{a?: ?int}'],
            ['array{0: int}'],
            ['array{0?: int}'],
            ['array{int, int}'],
            ['array{a: int, b: string}'],
            ['array{a?: int, b: string, 0: int, 1?: DateTime, hello: string}'],
            ['array{a: int, b: array{c: callable(): int}}'],
            ['?array{a: int}'],
            ['array{', false],
            ['array{a => int}', false],
            ['array{"a": int}'],
            ["array{'a': int}"],
            ["array{'\$ref': int}"],
            ['array{"$ref": int}'],
            ['array{' . \PHP_EOL . "\t\t\t\t \ta: int" . \PHP_EOL . "\t\t\t\t }"],
            ['array{' . \PHP_EOL . "\t\t\t\t \ta: int," . \PHP_EOL . "\t\t\t\t }"],
            ['array{' . \PHP_EOL . "\t\t\t\t \ta: int," . \PHP_EOL . "\t\t\t\t \tb: string," . \PHP_EOL . "\t\t\t\t }"],
            ['array{' . \PHP_EOL . "\t\t\t\t \ta: int" . \PHP_EOL . "\t\t\t\t \t, b: string" . \PHP_EOL . "\t\t\t\t \t, c: string" . \PHP_EOL . "\t\t\t\t }"],
            ['array{' . \PHP_EOL . "\t\t\t\t \ta: int," . \PHP_EOL . "\t\t\t\t \tb: string" . \PHP_EOL . "\t\t\t\t }"],
            ['array{a: int, b: int, ...}'],
            ['array{int, string, ...}'],
            ['array{...}'],
            ['array{' . \PHP_EOL . "\t\t\t\t \ta: int," . \PHP_EOL . "\t\t\t\t \t..." . \PHP_EOL . "\t\t\t\t }"],
            ['array{' . \PHP_EOL . "\t\t\t\t\ta: int," . \PHP_EOL . "\t\t\t\t\t...," . \PHP_EOL . "\t\t\t\t}"],
            ['array{int, ..., string}', false],
            ['list{' . \PHP_EOL . "\t\t\t\t \tint," . \PHP_EOL . "\t\t\t\t \tstring" . \PHP_EOL . "\t\t\t\t }"],
            ['non-empty-array{' . \PHP_EOL . "\t\t\t\t \tint," . \PHP_EOL . "\t\t\t\t \tstring" . \PHP_EOL . "\t\t\t\t }"],
            ['callable(): non-empty-array{int, string}'],
            ['callable(): non-empty-list{int, string}'],
            ['non-empty-list{' . \PHP_EOL . "\t\t\t\t \tint," . \PHP_EOL . "\t\t\t\t \tstring" . \PHP_EOL . "\t\t\t\t }"],
            ['array{...<string>}'],
            ['array{a: int, b?: int, ...<string>}'],
            ['array{a:int,b?:int,...<string>}'],
            ['array{a: int, b?: int, ...  ' . \PHP_EOL . '  <  ' . \PHP_EOL . '  string  ' . \PHP_EOL . '  >  ' . \PHP_EOL . '  ,  ' . \PHP_EOL . ' }'],
            ['array{...<int, string>}'],
            ['array{a: int, b?: int, ...<int, string>}'],
            ['array{a:int,b?:int,...<int,string>}'],
            ['array{a: int, b?: int, ...  ' . \PHP_EOL . '  <  ' . \PHP_EOL . '  int  ' . \PHP_EOL . '  ,  ' . \PHP_EOL . '  string  ' . \PHP_EOL . '  >  ' . \PHP_EOL . '  ,  ' . \PHP_EOL . '  }'],
            ['list{...<string>}'],
            ['list{int, int, ...<string>}'],
            ['list{int,int,...<string>}'],
            ['list{int, int, ...  ' . \PHP_EOL . '  <  ' . \PHP_EOL . '  string  ' . \PHP_EOL . '  >  ' . \PHP_EOL . '  ,  ' . \PHP_EOL . '  }'],
            ['list{0: int, 1?: int, ...<string>}'],
            ['list{0:int,1?:int,...<string>}'],
            ['list{0: int, 1?: int, ...  ' . \PHP_EOL . '  <  ' . \PHP_EOL . '  string  ' . \PHP_EOL . '  >  ' . \PHP_EOL . '  ,  ' . \PHP_EOL . '  }'],
            ['array{...<>}', false],
            ['array{...<int,>}', false],
            ['array{...<int, string,>}', false],
            ['array{...<int, string, string>}', false],
            ['list{...<>}', false],
            ['list{...<int,>}', false],
            ['list{...<int, string>}' /* , false */],
            ['callable(): Foo'],
            ['pure-callable(): Foo'],
            ['pure-Closure(): Foo'],
            ['callable(): ?Foo'],
            ['callable(): Foo<Bar>'],
            ['callable(): Foo<Bar>[]'],
            ['callable(): Foo|Bar'],
            ['callable(): Foo&Bar'],
            ['callable(): (Foo|Bar)'],
            ['callable(): (Foo&Bar)'],
            ['callable(): array{a: int}'],
            ['callable(A&...$a=, B&...=, C): Foo'],
            ['callable<A>(B): C'],
            ['callable<>(): void', false],
            ['Closure<T of Model>(T, int): (T|false)'],
            ['\Closure<Tx of X|Z, Ty of Y>(Tx, Ty): array{ Ty, Tx }'],
            ['(Foo\Bar<array<mixed, string>, (int | (string<foo> & bar)[])> | Lorem)'],
            ['array [ int ]'],
            ['array[ int ]'],
            ['self::TYPES[ int ]'],
            ["?\t\xa009"],
            ['Collection<array-key, int>[]'],
            ['int | Collection<array-key, int>[]'],
            ['array{foo: int}[]'],
            ['int | array{foo: int}[]'],
            ['$this[]'],
            ['int | $this[]'],
            ['callable(): int[]'],
            ['?int[]'],
            ['callable(mixed...): TReturn'],
            ["'foo'|'bar'"],
            ['Foo::FOO_CONSTANT'],
            ['123'],
            ['123_456'],
            ['_123'],
            ['123_', false],
            ['123.2'],
            ['123_456.789_012'],
            ['+0x10_20|+8e+2 | -0b11'],
            ['18_446_744_073_709_551_616|8.2023437675747321e-18_446_744_073_709_551_617'],
            ['"bar"'],
            ['Foo::FOO_*'],
            ['Foo::FOO_*BAR'],
            ['Foo::*FOO*'],
            ['Foo::A*B*C'],
            ['self::*BAR'],
            ['Foo::*'],
            ['Foo::**'],
            ['Foo::*a'],
            ['( "foo" | Foo::FOO_* )'],
            ['DateTimeImmutable::*|DateTime::*'],
            ['ParameterTier::*|null'],
            ['list<QueueAttributeName::*>'],
            ['array<' . \PHP_EOL . '  Foo' . \PHP_EOL . '>'],
            ['array<' . \PHP_EOL . '  Foo,' . \PHP_EOL . '  Bar' . \PHP_EOL . '>'],
            ['array<' . \PHP_EOL . '  Foo, Bar' . \PHP_EOL . '>'],
            ['array<' . \PHP_EOL . '  Foo,' . \PHP_EOL . '  array<' . \PHP_EOL . '    Bar' . \PHP_EOL . '  >' . \PHP_EOL . '>'],
            ['array<' . \PHP_EOL . '  Foo,' . \PHP_EOL . '  array<' . \PHP_EOL . '    Bar,' . \PHP_EOL . '  >' . \PHP_EOL . '>'],
            ['array{}'],
            ['array{}|int'],
            ['int|array{}'],
            ['callable(' . \PHP_EOL . '  Foo' . \PHP_EOL . '): void'],
            ['callable(' . \PHP_EOL . '  Foo,' . \PHP_EOL . '  Bar' . \PHP_EOL . '): void'],
            ['callable(' . \PHP_EOL . '  Foo, Bar' . \PHP_EOL . '): void'],
            ['callable(' . \PHP_EOL . '  Foo,' . \PHP_EOL . '  callable(' . \PHP_EOL . '    Bar' . \PHP_EOL . '  ): void' . \PHP_EOL . '): void'],
            ['callable(' . \PHP_EOL . '  Foo,' . \PHP_EOL . '  callable(' . \PHP_EOL . '    Bar,' . \PHP_EOL . '  ): void' . \PHP_EOL . '): void'],
            ['(Foo is Bar ? never : int)'],
            ['(Foo is not Bar ? never : int)'],
            ['(T is self::TYPE_STRING ? string : (T is self::TYPE_INT ? int : bool))'],
            ['(Foo is Bar|Baz ? never : int|string)'],
            ['(' . \PHP_EOL . '  TRandList is array ? array<TRandKey, TRandVal> : (' . \PHP_EOL . '  TRandList is XIterator ? XIterator<TRandKey, TRandVal> :' . \PHP_EOL . '  IteratorIterator<TRandKey, TRandVal>|LimitIterator<TRandKey, TRandVal>' . \PHP_EOL . '))'],
            ['($foo is Bar|Baz ? never : int|string)'],
            ['(' . \PHP_EOL . '  $foo is Bar|Baz' . \PHP_EOL . '    ? never' . \PHP_EOL . '    : int|string' . \PHP_EOL . ')'],
            ['?Currency::CURRENCY_*'],
            ['(T is Foo ? true : T is Bar ? false : null)'],
            ['(T is Foo ? T is Bar ? true : false : null)' /* , false */],
            ['($foo is Foo ? true : $foo is Bar ? false : null)'],
            ['($foo is Foo ? $foo is Bar ? true : false : null)' /* , false */],
            ['Foo<covariant Bar, Baz>'],
            ['Foo<Bar, contravariant Baz>'],
            ['Foo<covariant>', false],
            ['Foo<typovariant Bar>', false],
            ['Foo<Bar, *>'],
            ['object{a: int}'],
            ['object{a: ?int}'],
            ['object{a?: ?int}'],
            ['object{a: int, b: string}'],
            ['object{a: int, b: array{c: callable(): int}}'],
            ['object{a: int, b: object{c: callable(): int}}'],
            ['?object{a: int}'],
            ['object{', false],
            ['object{a => int}', false],
            ['object{int}' /* , false */],
            ['object{0: int}' /* , false */],
            ['object{0?: int}' /* , false */],
            ['object{"a": int}'],
            ["object{'a': int}"],
            ["object{'\$ref': int}"],
            ['object{"$ref": int}'],
            ['object{' . \PHP_EOL . "\t\t\t\t \ta: int" . \PHP_EOL . "\t\t\t\t }"],
            ['object{' . \PHP_EOL . "\t\t\t\t \ta: int," . \PHP_EOL . "\t\t\t\t }"],
            ['object{' . \PHP_EOL . "\t\t\t\t \ta: int," . \PHP_EOL . "\t\t\t\t \tb: string," . \PHP_EOL . "\t\t\t\t }"],
            ['object{' . \PHP_EOL . "\t\t\t\t \ta: int" . \PHP_EOL . "\t\t\t\t \t, b: string" . \PHP_EOL . "\t\t\t\t \t, c: string" . \PHP_EOL . "\t\t\t\t }"],
            ['object{' . \PHP_EOL . "\t\t\t\t \ta: int," . \PHP_EOL . "\t\t\t\t \tb: string" . \PHP_EOL . "\t\t\t\t }"],
            ['object{foo: int}[]'],
            ['int | object{foo: int}[]'],
            ['object{}'],
            ['object{}|int'],
            ['int|object{}'],
            ['object{attribute:string, value?:string}'],
            ['Closure(Foo): (Closure(Foo): Bar)'],
            ['callable(): ?int'],
            ['callable(): object{foo: int}'],
            ['callable(): object{foo: int}[]'],
            ['callable(): int[][][]'],
            ['callable(): (int[][][])'],
            ['(callable(): int[])[][]'],
            ['callable(): $this'],
            ['callable(): $this[]'],
            ['2.5|3'],
            ['callable(): 3.5'],
            ['callable(): 3.5[]'],
            ['callable(): (Foo)[]'],
            ['callable(): Foo::BAR'],
            ['callable(): Foo::*'],
            ['?Foo[]'],
            ['callable(): ?Foo[]'],
            ['?(Foo|Bar)'],
            ['Foo | (Bar & Baz)'],
            ['(?Foo) | Bar'],
            ['?(Foo&Bar)'],
            ['(?Foo)[]'],
            ['Foo | Bar | (Baz | Lorem)'],
            ['Closure(Container):($serviceId is class-string<TService> ? TService : mixed)'],
            ['int | object{foo: int}[]    '],
            ['array{' . \PHP_EOL . "\t\t\t\ta: int," . \PHP_EOL . "\t\t\t\tb: string" . \PHP_EOL . "\t\t\t }"],
            ['callable(Foo, Bar): void'],
            ['array{foo: int}'],
            ['object{foo: int}'],
            ['object{}[]'],
            ['int[][][]'],
            ['int[foo][bar][baz]'],
        ];
        foreach ($data as $test) {
            yield str_replace(\PHP_EOL, '<eol>', $test[0]) => $test;
        }
    }
}
