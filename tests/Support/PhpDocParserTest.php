<?php declare(strict_types=1);

namespace Lkrms\Tests\Support;

use Lkrms\Support\PhpDocParser;

final class PhpDocParserTest extends \Lkrms\Tests\TestCase
{
    public function testFromDocBlocks()
    {
        $docBlocks = [
            "/**
     * @param \$arg1 Description from ClassC (untyped)
     * @param string[] \$arg3
     * @return \$this Description from ClassC
     */",
            "/**
     * Summary from ClassB
     *
     * @param int|string \$arg1
     * @param array \$arg3
     * @return \$this
     */",
            "/**
     * Summary from ClassA
     *
     * Description from ClassA
     *
     * ```php
     * // code here
     * ```
     *
     * @param mixed \$arg1 Description from ClassA
     * @param string \$arg2 Description from ClassA
     * @param array \$arg3 Description from ClassA
     * @return \$this
     */",
        ];

        $phpDoc = PhpDocParser::fromDocBlocks($docBlocks);

        $this->assertEquals($phpDoc->Summary, 'Summary from ClassB');
        $this->assertEquals($phpDoc->Description, "Description from ClassA

```php
// code here
```");
        $this->assertEquals($phpDoc->TagLines, [
            '@param $arg1 Description from ClassC (untyped)',
            '@param string[] $arg3',
            '@return $this Description from ClassC',
            '@param int|string $arg1',
            '@param array $arg3',
            '@return $this',
            '@param mixed $arg1 Description from ClassA',
            '@param string $arg2 Description from ClassA',
            '@param array $arg3 Description from ClassA',
        ]);
        $this->assertEquals($phpDoc->Tags, [
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
        ]);
        $this->assertEquals($phpDoc->Params, [
            'arg1' => [
                'type'        => 'int|string',
                'description' => 'Description from ClassC (untyped)',
            ],
            'arg3' => [
                'type'        => 'string[]',
                'description' => 'Description from ClassA',
            ],
            'arg2' => [
                'type'        => 'string',
                'description' => 'Description from ClassA',
            ],
        ]);
        $this->assertEquals($phpDoc->Return, [
            'type'        => '$this',
            'description' => 'Description from ClassC',
        ]);
    }

    public function testVarTags()
    {
        $docBlocks = [
            '/** @var int $int This is a counter. */',
            "/**
     * Full docblock with a summary.
     *
     * @var int
     */",
            '/** @var string|null Short docblock, should contain a description. */',
            "/**
      * @var string \$name        Should contain a description
      * @var string \$description Should contain a description
      */",
            '/** @var int */',
            '/** @var */',
        ];

        foreach ($docBlocks as $docBlock) {
            $phpDocs[] = (new PhpDocParser($docBlock))->Var;
        }

        $this->assertEquals([
            [['name' => '$int', 'type' => 'int', 'description' => 'This is a counter.']],
            [['name' => null, 'type' => 'int', 'description' => 'Full docblock with a summary.']],
            [['name' => null, 'type' => '?string', 'description' => 'Short docblock, should contain a description.']],
            [
                ['name' => '$name', 'type' => 'string', 'description' => 'Should contain a description'],
                ['name' => '$description', 'type' => 'string', 'description' => 'Should contain a description'],
            ],
            [['name' => null, 'type' => 'int', 'description' => null]],
            [],
        ], $phpDocs);
    }

    public function testFences()
    {
        $docBlock = "/**
 * Summary
 *
 * Description with multiple code blocks:
 *
 * ```php
 * \$this->doSomething();
 * ```
 *
 * Three, to be precise (including within the `@var`):
 *
 * ```php
 * \$this->doSomethingElse();
 * ```
 *
 * @var callable|null
 * ```php
 * callback(string \$value): string
 * ```
 */";
        $phpDoc = new PhpDocParser($docBlock);
        $this->assertEquals($phpDoc->Summary, 'Summary');
        $this->assertEquals($phpDoc->Description, "Description with multiple code blocks:

```php
\$this->doSomething();
```

Three, to be precise (including within the `@var`):

```php
\$this->doSomethingElse();
```

```php
callback(string \$value): string
```");
        $this->assertEquals($phpDoc->Var, [['name' => null, 'type' => '?callable', 'description' => 'Summary']]);
    }
}
