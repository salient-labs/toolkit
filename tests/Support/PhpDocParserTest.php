<?php declare(strict_types=1);

namespace Lkrms\Tests\Support;

use Lkrms\Support\PhpDoc\PhpDoc;
use Lkrms\Support\PhpDoc\PhpDocTemplateTag;
use Lkrms\Support\PhpDoc\PhpDocVarTag;
use UnexpectedValueException;

final class PhpDocParserTest extends \Lkrms\Tests\TestCase
{
    /**
     * @dataProvider invalidDocBlockProvider
     */
    public function testInvalidDocBlock(string $docBlock)
    {
        $this->expectException(UnexpectedValueException::class);
        new PhpDoc($docBlock);
    }

    public static function invalidDocBlockProvider(): array
    {
        return [
            'missing asterisk' => [
                "/**\n\n*/",
            ],
        ];
    }

    public function testFromDocBlocks()
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
            EOF, $phpDoc->Description);
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
        $this->assertSame(null, $phpDoc->Return->Name);
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
    ) {
        $phpDoc = new PhpDoc($docBlock);
        $this->assertSame($summary, $phpDoc->Summary);
        $this->assertSame($description, $phpDoc->Description);
        $this->assertCount(count($varKeys), $phpDoc->Vars);
        foreach ($varKeys as $i => $key) {
            $this->assertArrayHasKey($key, $phpDoc->Vars);
            $this->assertSame($phpDoc->Vars[$key]->Name, $varNames[$i]);
            $this->assertSame($phpDoc->Vars[$key]->Type, $varTypes[$i]);
            $this->assertSame($phpDoc->Vars[$key]->Description, $varDescriptions[$i]);
        }
    }

    public static function varTagsProvider()
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
                "And a description.\n\nAnd a variable description.",
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
                ['?string'],
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

    public function testTemplateTags()
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
        $this->assertEquals('Summary', $phpDoc->Summary);
        $this->assertEquals(null, $phpDoc->Description);
        $this->assertEquals('mixed', $phpDoc->Templates['T']->Type);
    }

    public function testTemplateInheritance()
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
             * @template TKey of int|string
             * @template TValue of object
             */
            EOF;
        $phpDoc = new PhpDoc($docBlock, $classDocBlock);
        $this->assertEquals('Summary', $phpDoc->Summary);
        $this->assertEquals(null, $phpDoc->Description);
        $this->assertCount(4, $phpDoc->Templates);
        $this->assertSame('T', $phpDoc->Templates['T']->Name);
        $this->assertSame('mixed', $phpDoc->Templates['T']->Type);
        $this->assertSame(null, $phpDoc->Templates['T']->Description);
        $this->assertSame('TArray', $phpDoc->Templates['TArray']->Name);
        $this->assertSame('?array', $phpDoc->Templates['TArray']->Type);
        $this->assertSame(null, $phpDoc->Templates['TArray']->Description);
        $this->assertSame('TKey', $phpDoc->Templates['TKey']->Name);
        $this->assertSame('int|string', $phpDoc->Templates['TKey']->Type);
        $this->assertSame(null, $phpDoc->Templates['TKey']->Description);
        $this->assertSame('TValue', $phpDoc->Templates['TValue']->Name);
        $this->assertSame('object', $phpDoc->Templates['TValue']->Type);
        $this->assertSame(null, $phpDoc->Templates['TValue']->Description);
    }

    public function testFences()
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

        $phpDoc = new PhpDoc($docBlock, null, true);

        $this->assertEquals($phpDoc->Summary, 'Summary');
        $this->assertEquals($phpDoc->Description, <<<'EOF'
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
            EOF);
        $this->assertCount(1, $phpDoc->Vars);
        $this->assertSame(null, $phpDoc->Vars[0]->Name ?? null);
        $this->assertSame('?callable', $phpDoc->Vars[0]->Type ?? null);
        $this->assertSame(null, $phpDoc->Vars[0]->Description ?? null);
    }

    public function testBlankLines()
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
             * @template T of object
             *
             *
             */
            EOF;
        $phpDoc = new PhpDoc($docBlock);
        $this->assertSame('Summary', $phpDoc->Summary);
        $this->assertSame('Parts are surrounded by superfluous blank lines.', $phpDoc->Description);
        $this->assertCount(1, $phpDoc->Templates);
        $this->assertSame('T', $phpDoc->Templates['T']->Name ?? null);
        $this->assertSame('object', $phpDoc->Templates['T']->Type ?? null);
    }

    public function testNoBlankLineAfterSummary()
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
        $this->assertSame(null, $phpDoc->Description);
        $this->assertCount(0, $phpDoc->Templates);
    }

    public function testMultiLineTagDescription()
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
        $this->assertSame(null, $phpDoc->Params['arg']->Type ?? null);
        $this->assertSame('Description of $arg', $phpDoc->Params['arg']->Description ?? null);
    }

    public function testEol()
    {
        $docBlock =
            "/**\r\n * Summary \r\n *  \r\n * Has trailing spaces and CRLF end-of-lines. \r\n *  \r\n * @internal \r\n */";
        $phpDoc = new PhpDoc($docBlock);
        $this->assertEquals('Summary', $phpDoc->Summary);
        $this->assertEquals('Has trailing spaces and CRLF end-of-lines.', $phpDoc->Description);
    }
}
