<?php declare(strict_types=1);

namespace Lkrms\Tests\Support\PhpDoc;

use Lkrms\Support\PhpDoc\PhpDoc;
use Lkrms\Tests\TestCase;
use Salient\Core\Catalog\Regex;
use Salient\Core\Exception\InvalidArgumentException;
use Salient\Core\Utility\Pcre;
use Salient\Core\Utility\Str;

final class PhpDocTest extends TestCase
{
    /**
     * @dataProvider invalidDocBlockProvider
     */
    public function testInvalidDocBlock(string $docBlock): void
    {
        $this->expectException(InvalidArgumentException::class);
        new PhpDoc($docBlock);
    }

    /**
     * @return array<string,array{string}>
     */
    public static function invalidDocBlockProvider(): array
    {
        return [
            'missing asterisk' => [
                <<<'EOF'
                /**

                */
                EOF,
            ],
        ];
    }

    public function testFromDocBlocks(): void
    {
        $docBlocks = [
            <<<'EOF'
            /**
             * @param $arg1 Description from ClassC (untyped)
             * @param string[] $arg3
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

        $phpDoc = PhpDoc::fromDocBlocks($docBlocks);

        $this->assertSame('Summary from ClassB', $phpDoc->Summary);
        $this->assertSame(<<<'EOF'
            Description from ClassA

            ```php
            // code here
            ```
            EOF, Str::eolToNative($phpDoc->Description));
        $this->assertSame([
            '@param $arg1 Description from ClassC (untyped)',
            '@param string[] $arg3',
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
        $this->assertCount(3, $phpDoc->Params);
        $this->assertSame('arg1', $phpDoc->Params['arg1']->Name);
        $this->assertSame('int|string', $phpDoc->Params['arg1']->Type);
        $this->assertSame('Description from ClassC (untyped)', $phpDoc->Params['arg1']->Description);
        $this->assertSame('arg3', $phpDoc->Params['arg3']->Name);
        $this->assertSame('string[]', $phpDoc->Params['arg3']->Type);
        $this->assertSame('Description from ClassA', $phpDoc->Params['arg3']->Description);
        $this->assertSame('arg2', $phpDoc->Params['arg2']->Name);
        $this->assertSame('string', $phpDoc->Params['arg2']->Type);
        $this->assertSame('Description from ClassA', $phpDoc->Params['arg2']->Description);
        $this->assertNull($phpDoc->Return->Name);
        $this->assertSame('$this', $phpDoc->Return->Type);
        $this->assertSame('Description from ClassC', $phpDoc->Return->Description);
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
        $phpDoc = new PhpDoc($docBlock);
        $this->assertSame($summary, $phpDoc->Summary);
        $this->assertSame($description, Str::eolToNative($phpDoc->Description));
        $this->assertCount(count($varKeys), $phpDoc->Vars);
        foreach ($varKeys as $i => $key) {
            $this->assertArrayHasKey($key, $phpDoc->Vars);
            $this->assertSame($varNames[$i], $phpDoc->Vars[$key]->Name);
            $this->assertSame($varTypes[$i], $phpDoc->Vars[$key]->Type);
            $this->assertSame($varDescriptions[$i], $phpDoc->Vars[$key]->Description);
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
                /** @var int $int This is a counter. */
                EOF,
                'This is a counter.',
                null,
                ['$int'],
                ['$int'],
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
                ['$name', '$description'],
                ['$name', '$description'],
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
            [
                <<<'EOF'
                /** @var */
                EOF,
                null,
                null,
                [],
                [],
                [],
                [],
            ]
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
        $phpDoc = new PhpDoc($docBlock);
        $this->assertSame('Summary', $phpDoc->Summary);
        $this->assertNull($phpDoc->Description);
        $this->assertSame('mixed', $phpDoc->Templates['T']->Type);
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
        $phpDoc = new PhpDoc($docBlock, $classDocBlock);
        $this->assertSame('Summary', $phpDoc->Summary);
        $this->assertNull($phpDoc->Description);
        $this->assertCount(4, $phpDoc->Templates);
        $this->assertSame('T', $phpDoc->Templates['T']->Name);
        $this->assertSame('mixed', $phpDoc->Templates['T']->Type);
        $this->assertNull($phpDoc->Templates['T']->Description);
        $this->assertSame('TArray', $phpDoc->Templates['TArray']->Name);
        $this->assertSame('array|null', $phpDoc->Templates['TArray']->Type);
        $this->assertNull($phpDoc->Templates['TArray']->Description);
        $this->assertSame('TKey', $phpDoc->Templates['TKey']->Name);
        $this->assertSame('array-key', $phpDoc->Templates['TKey']->Type);
        $this->assertNull($phpDoc->Templates['TKey']->Description);
        $this->assertSame('TValue', $phpDoc->Templates['TValue']->Name);
        $this->assertSame('object', $phpDoc->Templates['TValue']->Type);
        $this->assertNull($phpDoc->Templates['TValue']->Description);
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

        $phpDoc = new PhpDoc($docBlock, null, null, null, true);

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
        $this->assertNull($phpDoc->Vars[0]->Name ?? null);
        $this->assertSame('?callable', $phpDoc->Vars[0]->Type ?? null);
        $this->assertNull($phpDoc->Vars[0]->Description ?? null);
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
        $phpDoc = new PhpDoc($docBlock);
        $this->assertSame('Summary', $phpDoc->Summary);
        $this->assertSame('Parts are surrounded by superfluous blank lines.', $phpDoc->Description);
        $this->assertCount(2, $phpDoc->Templates);
        $this->assertSame('T0', $phpDoc->Templates['T0']->Name ?? null);
        $this->assertSame('object', $phpDoc->Templates['T0']->Type ?? null);
        $this->assertSame('T1', $phpDoc->Templates['T1']->Name ?? null);
        $this->assertSame('mixed', $phpDoc->Templates['T1']->Type ?? null);
        $this->assertCount(1, $phpDoc->Vars);
        $this->assertSame('class-string<T0>|null', $phpDoc->Vars['$Class']->Type ?? null);
        $this->assertCount(1, $phpDoc->Params);
        $this->assertSame('class-string<T0>|null', $phpDoc->Params['class']->Type ?? null);
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
        $phpDoc = new PhpDoc($docBlock);
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
        $phpDoc = new PhpDoc($docBlock);
        $this->assertCount(1, $phpDoc->Params);
        $this->assertSame('arg', $phpDoc->Params['arg']->Name ?? null);
        $this->assertNull($phpDoc->Params['arg']->Type ?? null);
        $this->assertSame('Description of $arg', $phpDoc->Params['arg']->Description ?? null);
    }

    /**
     * @dataProvider eolProvider
     */
    public function testEol(string $docBlock, string $summary, string $description): void
    {
        $phpDoc = new PhpDoc($docBlock);
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
        $regex = Pcre::delimit('^' . Regex::PHPDOC_TYPE . '$', '/');
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
            ['(
  string
  &
  int
)'],
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
            ["array{
\t\t\t\t \ta: int
\t\t\t\t }"],
            ["array{
\t\t\t\t \ta: int,
\t\t\t\t }"],
            ["array{
\t\t\t\t \ta: int,
\t\t\t\t \tb: string,
\t\t\t\t }"],
            ["array{
\t\t\t\t \ta: int
\t\t\t\t \t, b: string
\t\t\t\t \t, c: string
\t\t\t\t }"],
            ["array{
\t\t\t\t \ta: int,
\t\t\t\t \tb: string
\t\t\t\t }"],
            ['array{a: int, b: int, ...}'],
            ['array{int, string, ...}'],
            ['array{...}'],
            ["array{
\t\t\t\t \ta: int,
\t\t\t\t \t...
\t\t\t\t }"],
            ["array{
\t\t\t\t\ta: int,
\t\t\t\t\t...,
\t\t\t\t}"],
            ['array{int, ..., string}', false],
            ["list{
\t\t\t\t \tint,
\t\t\t\t \tstring
\t\t\t\t }"],
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
            // ['123_'],
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
            ['array<
  Foo
>'],
            ['array<
  Foo,
  Bar
>'],
            ['array<
  Foo, Bar
>'],
            ['array<
  Foo,
  array<
    Bar
  >
>'],
            ['array<
  Foo,
  array<
    Bar,
  >
>'],
            ['array{}'],
            ['array{}|int'],
            ['int|array{}'],
            ['callable(
  Foo
): void'],
            ['callable(
  Foo,
  Bar
): void'],
            ['callable(
  Foo, Bar
): void'],
            ['callable(
  Foo,
  callable(
    Bar
  ): void
): void'],
            ['callable(
  Foo,
  callable(
    Bar,
  ): void
): void'],
            ['(Foo is Bar ? never : int)'],
            ['(Foo is not Bar ? never : int)'],
            ['(T is self::TYPE_STRING ? string : (T is self::TYPE_INT ? int : bool))'],
            ['(Foo is Bar|Baz ? never : int|string)'],
            ['(
  TRandList is array ? array<TRandKey, TRandVal> : (
  TRandList is XIterator ? XIterator<TRandKey, TRandVal> :
  IteratorIterator<TRandKey, TRandVal>|LimitIterator<TRandKey, TRandVal>
))'],
            ['($foo is Bar|Baz ? never : int|string)'],
            ['(
  $foo is Bar|Baz
    ? never
    : int|string
)'],
            ['?Currency::CURRENCY_*'],
            ['(T is Foo ? true : T is Bar ? false : null)'],
            ['(T is Foo ? T is Bar ? true : false : null)'],
            ['($foo is Foo ? true : $foo is Bar ? false : null)'],
            ['($foo is Foo ? $foo is Bar ? true : false : null)'],
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
            // ['object{int}', false],
            // ['object{0: int}', false],
            // ['object{0?: int}', false],
            ['object{"a": int}'],
            ["object{'a': int}"],
            ["object{'\$ref': int}"],
            ['object{"$ref": int}'],
            ["object{
\t\t\t\t \ta: int
\t\t\t\t }"],
            ["object{
\t\t\t\t \ta: int,
\t\t\t\t }"],
            ["object{
\t\t\t\t \ta: int,
\t\t\t\t \tb: string,
\t\t\t\t }"],
            ["object{
\t\t\t\t \ta: int
\t\t\t\t \t, b: string
\t\t\t\t \t, c: string
\t\t\t\t }"],
            ["object{
\t\t\t\t \ta: int,
\t\t\t\t \tb: string
\t\t\t\t }"],
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
            ['int | object{foo: int}[]'],
            ['int | object{foo: int}[]    '],
            ["array{
\t\t\t\ta: int,
\t\t\t\tb: string
\t\t\t }"],
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
