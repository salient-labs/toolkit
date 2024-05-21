<?php declare(strict_types=1);

namespace Salient\Tests\PHPDoc;

use Salient\Core\Utility\Pcre;
use Salient\Core\Utility\Str;
use Salient\PHPDoc\Exception\InvalidTagValueException;
use Salient\PHPDoc\PHPDoc;
use Salient\PHPDoc\PHPDocRegex;
use Salient\Tests\TestCase;
use InvalidArgumentException;

/**
 * @covers \Salient\PHPDoc\PHPDoc
 * @covers \Salient\PHPDoc\Tag\AbstractTag
 * @covers \Salient\PHPDoc\Tag\ParamTag
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
        new PHPDoc($docBlock);
    }

    /**
     * @return array<string,array{string}>
     */
    public static function invalidDocBlockProvider(): array
    {
        return [
            'no whitespace after opening delimiter' => [
                '/***/',
            ],
            'missing asterisk' => [
                <<<'EOF'
/**
 *

 */
EOF,
            ],
        ];
    }

    /**
     * @dataProvider docBlockWithNoSummaryProvider
     */
    public function testDocBlockWithNoSummary(string $docBlock): void
    {
        $phpDoc = new PHPDoc($docBlock);
        $this->assertNull($phpDoc->Summary);
        $this->assertNull($phpDoc->Description);
    }

    /**
     * @return array<array{string}>
     */
    public static function docBlockWithNoSummaryProvider(): array
    {
        return [
            [
                '/** */',
            ],
            [
                <<<'EOF'
/**
 */
EOF,
            ],
            [
                <<<'EOF'
/**
 *
 */
EOF,
            ],
            [
                <<<'EOF'
/**
 * @internal
 */
EOF,
            ],
        ];
    }

    /**
     * @dataProvider docBlockWithNoDescriptionProvider
     */
    public function testDocBlockWithNoDescription(string $docBlock): void
    {
        $phpDoc = new PHPDoc($docBlock);
        $this->assertSame('Summary', $phpDoc->Summary);
        $this->assertNull($phpDoc->Description);
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
 *
 *
 *
 * @internal
 */
EOF],
        ];
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
 * @param int|string $arg1
 * @param array $arg3
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

        $phpDoc = PHPDoc::fromDocBlocks($docBlocks);

        $this->assertNotNull($phpDoc);
        $this->assertSame('Summary from ClassB', $phpDoc->Summary);
        $this->assertSame("Description from ClassA\n\n```php\n// code here\n```", $phpDoc->Description);
        $this->assertSame([
            '@param $arg1 Description from ClassC (untyped)',
            '@param string[] $arg3',
            '@param bool &$arg4',
            '@param mixed ...$arg5',
            '@return $this Description from ClassC',
            '@param int|string $arg1',
            '@param array $arg3',
            '@return $this',
            '@param mixed $arg1 Description from ClassA',
            '@param string $arg2 Description from ClassA',
            '@param array $arg3 Description from ClassA',
        ], $phpDoc->Tags);
        $this->assertSame([
            'param' => [
                '$arg1 Description from ClassC (untyped)',
                'string[] $arg3',
                'bool &$arg4',
                'mixed ...$arg5',
                'int|string $arg1',
                'array $arg3',
                'mixed $arg1 Description from ClassA',
                'string $arg2 Description from ClassA',
                'array $arg3 Description from ClassA',
            ],
            'return' => [
                '$this Description from ClassC',
                '$this',
            ],
        ], $phpDoc->TagsByName);

        $this->assertSame(
            ['arg1', 'arg3', 'arg4', 'arg5', 'arg2'],
            array_keys($phpDoc->Params),
        );

        $tag = $phpDoc->Params['arg1'];
        $this->assertSame('arg1', $tag->getName());
        $this->assertSame('int|string', $tag->getType());
        $this->assertSame('Description from ClassC (untyped)', $tag->getDescription());
        $this->assertFalse($tag->isPassedByReference());
        $this->assertFalse($tag->isVariadic());

        $tag = $phpDoc->Params['arg3'];
        $this->assertSame('arg3', $tag->getName());
        $this->assertSame('string[]', $tag->getType());
        $this->assertSame('Description from ClassA', $tag->getDescription());
        $this->assertFalse($tag->isPassedByReference());
        $this->assertFalse($tag->isVariadic());

        $tag = $phpDoc->Params['arg4'];
        $this->assertSame('arg4', $tag->getName());
        $this->assertSame('bool', $tag->getType());
        $this->assertNull($tag->getDescription());
        $this->assertTrue($tag->isPassedByReference());
        $this->assertFalse($tag->isVariadic());

        $tag = $phpDoc->Params['arg5'];
        $this->assertSame('arg5', $tag->getName());
        $this->assertSame('mixed', $tag->getType());
        $this->assertNull($tag->getDescription());
        $this->assertFalse($tag->isPassedByReference());
        $this->assertTrue($tag->isVariadic());

        $tag = $phpDoc->Params['arg2'];
        $this->assertSame('arg2', $tag->getName());
        $this->assertSame('string', $tag->getType());
        $this->assertSame('Description from ClassA', $tag->getDescription());
        $this->assertFalse($tag->isPassedByReference());
        $this->assertFalse($tag->isVariadic());

        $this->assertNotNull($tag = $phpDoc->Return);
        $this->assertSame('$this', $tag->getType());
        $this->assertSame('Description from ClassC', $tag->getDescription());
    }

    /**
     * @dataProvider paramTagsProvider
     *
     * @param string[] $paramNames
     * @param array<string|null> $paramTypes
     * @param array<string|null> $paramDescriptions
     * @param bool[] $paramIsReferenceValues
     * @param bool[] $paramIsVariadicValues
     */
    public function testParamTags(
        string $docBlock,
        array $paramNames,
        array $paramTypes,
        array $paramDescriptions,
        array $paramIsReferenceValues,
        array $paramIsVariadicValues
    ): void {
        $phpDoc = new PHPDoc($docBlock);
        $this->assertSame($paramNames, array_keys($phpDoc->Params));
        foreach ($paramNames as $i => $name) {
            $this->assertSame($name, ($param = $phpDoc->Params[$name])->getName());
            $this->assertSame($paramTypes[$i], $param->getType());
            $this->assertSame($paramDescriptions[$i], $param->getDescription());
            $this->assertSame($paramIsReferenceValues[$i], $param->isPassedByReference());
            $this->assertSame($paramIsVariadicValues[$i], $param->isVariadic());
        }
    }

    /**
     * @return array<array{string,string[],array<string|null>,array<string|null>,bool[],bool[]}>
     */
    public static function paramTagsProvider(): array
    {
        return [
            [
                <<<'EOF'
/**
 * @param (int|string)[] & ... $idListsByReference
 */
EOF,
                ['idListsByReference'],
                ['(int|string)[]'],
                [null],
                [true],
                [true],
            ],
            [
                <<<'EOF'
/**
 * @param (int|string)[] & ... $idListsByReference Description of $idListsByReference
 */
EOF,
                ['idListsByReference'],
                ['(int|string)[]'],
                ['Description of $idListsByReference'],
                [true],
                [true],
            ],
        ];
    }

    /**
     * @dataProvider varTagsProvider
     *
     * @param array<int|string> $varKeys
     * @param array<string|null> $varNames
     * @param array<string|null> $varTypes
     * @param array<string|null> $varDescriptions
     */
    public function testVarTags(
        string $docBlock,
        ?string $summary,
        ?string $description,
        array $varKeys,
        array $varNames,
        array $varTypes,
        array $varDescriptions
    ): void {
        $phpDoc = new PHPDoc($docBlock);
        $this->assertSame($summary, $phpDoc->Summary);
        $this->assertSame($description, Str::eolToNative($phpDoc->Description));
        $this->assertCount(count($varKeys), $phpDoc->Vars);
        foreach ($varKeys as $i => $key) {
            $this->assertArrayHasKey($key, $phpDoc->Vars);
            $this->assertSame($varNames[$i], ($var = $phpDoc->Vars[$key])->getName());
            $this->assertSame($varTypes[$i], $var->getType());
            $this->assertSame($varDescriptions[$i], $var->getDescription());
        }
    }

    /**
     * @return array<array{string,string|null,string|null,array<int|string>,array<string|null>,array<string|null>,array<string|null>}>
     */
    public static function varTagsProvider(): array
    {
        return [
            [
                <<<'EOF'
/** @var int $counter This is a counter. */
EOF,
                'This is a counter.',
                null,
                ['counter'],
                ['counter'],
                ['int'],
                [null],
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
            ],
            [
                <<<'EOF'
/** @var string|null Short docblock, should have a summary. */
EOF,
                'Short docblock, should have a summary.',
                null,
                [0],
                [null],
                ['string|null'],
                [null],
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
            ],
        ];
    }

    public function testTemplateTags(): void
    {
        $docBlock = <<<'EOF'
/**
 * Summary
 *
 * @template T
 * @param class-string<T> $id
 * @return T
 */
EOF;
        $phpDoc = new PHPDoc($docBlock);
        $this->assertSame('Summary', $phpDoc->Summary);
        $this->assertNull($phpDoc->Description);
        $this->assertSame('mixed', $phpDoc->Templates['T']->getType());
    }

    public function testTemplateInheritance(): void
    {
        $docBlock = <<<'EOF'
/**
 * Summary
 *
 * @template T
 * @template TArray of array|null
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
 * @template T of string
 * @template TKey of array-key
 * @template TValue of object
 */
EOF;
        $phpDoc = new PHPDoc($docBlock, $classDocBlock);
        $this->assertSame('Summary', $phpDoc->Summary);
        $this->assertNull($phpDoc->Description);
        $this->assertCount(4, $phpDoc->Templates);
        $this->assertSame('T', $phpDoc->Templates['T']->getName());
        $this->assertSame('mixed', $phpDoc->Templates['T']->getType());
        $this->assertSame('TArray', $phpDoc->Templates['TArray']->getName());
        $this->assertSame('array|null', $phpDoc->Templates['TArray']->getType());
        $this->assertSame('TKey', $phpDoc->Templates['TKey']->getName());
        $this->assertSame('array-key', $phpDoc->Templates['TKey']->getType());
        $this->assertSame('TValue', $phpDoc->Templates['TValue']->getName());
        $this->assertSame('object', $phpDoc->Templates['TValue']->getType());
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

        $this->assertSame('Summary', $phpDoc->Summary);
        $this->assertSame(<<<'EOF'
Description with multiple code blocks:

```php
$this->doSomething();
```

Three, to be precise (including within the `@var`):

```
@var Not this `@var`, though. It's in a fence.
```

Something like this:
```php
callback(string $value): string
```
EOF, Str::eolToNative($phpDoc->Description));
        $this->assertCount(1, $phpDoc->Vars);
        $this->assertNull($phpDoc->Vars[0]->getName());
        $this->assertSame('callable|null', $phpDoc->Vars[0]->getType());
        $this->assertNull($phpDoc->Vars[0]->getDescription());
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
 * @internal
 *
 *
 * @template T0 of object
 *
 *
 * @template T1
 *
 *
 * @var class-string<T0>|null $Class
 *
 *
 * @param class-string<T0>|null $class
 *
 *
 * @inheritDoc
 *
 *
 */
EOF;
        $phpDoc = new PHPDoc($docBlock);
        $this->assertSame('Summary', $phpDoc->Summary);
        $this->assertSame('Parts are surrounded by superfluous blank lines.', $phpDoc->Description);
        $this->assertCount(2, $phpDoc->Templates);
        $this->assertSame('T0', $phpDoc->Templates['T0']->getName());
        $this->assertSame('object', $phpDoc->Templates['T0']->getType());
        $this->assertSame('T1', $phpDoc->Templates['T1']->getName());
        $this->assertSame('mixed', $phpDoc->Templates['T1']->getType());
        $this->assertCount(1, $phpDoc->Vars);
        $this->assertSame('class-string<T0>|null', $phpDoc->Vars['Class']->getType());
        $this->assertCount(1, $phpDoc->Params);
        $this->assertSame('class-string<T0>|null', $phpDoc->Params['class']->getType());
    }

    public function testNoBlankLineAfterSummary(): void
    {
        $docBlock = <<<'EOF'
/**
 * Summary
 * @internal
 * @template T of object
 */
EOF;
        $phpDoc = new PHPDoc($docBlock);
        $this->assertSame('Summary @internal @template T of object', $phpDoc->Summary);
        $this->assertNull($phpDoc->Description);
        $this->assertCount(0, $phpDoc->Templates);
    }

    public function testMultiLineTagDescription(): void
    {
        $docBlock = <<<'EOF'
/**
 * @param $arg
 * Description of $arg
 */
EOF;
        $phpDoc = new PHPDoc($docBlock);
        $this->assertCount(1, $phpDoc->Params);
        $this->assertSame('arg', $phpDoc->Params['arg']->getName());
        $this->assertNull($phpDoc->Params['arg']->getType());
        $this->assertSame('Description of $arg', $phpDoc->Params['arg']->getDescription());
    }

    /**
     * @dataProvider invalidTagProvider
     *
     * @param class-string|null $class
     */
    public function testInvalidTag(
        string $expectedMessage,
        string $docBlock,
        ?string $class = null,
        ?string $member = null
    ): void {
        $this->expectException(InvalidTagValueException::class);
        $this->expectExceptionMessage($expectedMessage);
        new PHPDoc($docBlock, null, $class, $member);
    }

    /**
     * @return array<array{string,string,2?:string|null,3?:string|null}>
     */
    public static function invalidTagProvider(): array
    {
        return [
            [
                'No name for @param in DocBlock',
                '/** @param */',
            ],
            [
                'No name for @param in DocBlock of Foo',
                '/** @param */',
                'Foo',
            ],
            [
                'No name for @param in DocBlock of Foo::bar()',
                '/** @param */',
                'Foo',
                'bar()',
            ],
            [
                "Invalid name 'notAVariable' for @param in DocBlock",
                '/** @param bool notAVariable */',
            ],
            [
                'No type for @return in DocBlock',
                '/** @return */',
            ],
            [
                'No type for @return in DocBlock',
                '/** @return /notAType */',
            ],
            [
                'No type for @var in DocBlock',
                '/** @var */',
            ],
            [
                'No type for @var in DocBlock',
                '/** @var $variable */',
            ],
            [
                'No name for @template in DocBlock',
                '/** @template */',
            ],
        ];
    }

    /**
     * @dataProvider eolProvider
     */
    public function testEol(string $docBlock, string $summary, string $description): void
    {
        $phpDoc = new PHPDoc($docBlock);
        $this->assertSame($summary, $phpDoc->Summary);
        $this->assertSame($description, $phpDoc->Description);
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
        $regex = Pcre::delimit('^' . PHPDocRegex::PHPDOC_TYPE . '$', '/');
        if ($expectMatch) {
            $this->assertMatchesRegularExpression($regex, trim($phpDocType));
        } else {
            $this->assertDoesNotMatchRegularExpression($regex, trim($phpDocType));
        }
    }

    /**
     * @return array<array{string,1?:bool}>
     */
    public static function typeRegexProvider(): array
    {
        // Extracted from tests/PHPStan/Parser/TypeParserTest.php in
        // phpstan/phpdoc-parser
        return [
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
            ['callable(): Foo'],
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
            ['callable(): Foo'],
            ['callable(): (Foo)[]'],
            ['callable(): Foo::BAR'],
            ['callable(): Foo::*'],
            ['?Foo[]'],
            ['callable(): ?Foo'],
            ['callable(): ?Foo[]'],
            ['?(Foo|Bar)'],
            ['Foo | (Bar & Baz)'],
            ['(?Foo) | Bar'],
            ['?(Foo|Bar)'],
            ['?(Foo&Bar)'],
            ['?Foo[]'],
            ['(?Foo)[]'],
            ['Foo | Bar | (Baz | Lorem)'],
            ['Closure(Container):($serviceId is class-string<TService> ? TService : mixed)'],
            ['int | object{foo: int}[]'],
            ['int | object{foo: int}[]    '],
            ['array{' . \PHP_EOL . "\t\t\t\ta: int," . \PHP_EOL . "\t\t\t\tb: string" . \PHP_EOL . "\t\t\t }"],
            ['callable(Foo, Bar): void'],
            ['$this'],
            ['array{foo: int}'],
            ['array{}'],
            ['object{foo: int}'],
            ['object{}'],
            ['object{}[]'],
            ['int[][][]'],
            ['int[foo][bar][baz]'],
        ];
    }
}
