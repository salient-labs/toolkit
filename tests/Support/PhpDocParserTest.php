<?php

declare(strict_types=1);

namespace Lkrms\Tests\Support;

use Lkrms\Support\PhpDocParser;

final class PhpDocParserTest extends \Lkrms\Tests\TestCase
{
    public function testFromDocBlocks()
    {
        $docBlocks = [
            '/**
     * @param $arg1 Description from ClassC (untyped)
     * @param string[] $arg3
     * @return $this Description from ClassC
     */',
            '/**
     * Summary from ClassB
     *
     * @param int|string $arg1
     * @param array $arg3
     * @return $this
     */',
            '/**
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
     */',
        ];

        $phpDoc = PhpDocParser::fromDocBlocks($docBlocks);

        $this->assertEquals($phpDoc->Summary, 'Summary from ClassB');
        $this->assertEquals($phpDoc->Description, 'Description from ClassA

```php
// code here
```');
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
            'arg1'            => [
                'type'        => 'int|string',
                'description' => 'Description from ClassC (untyped)',
            ],
            'arg3'            => [
                'type'        => 'string[]',
                'description' => 'Description from ClassA',
            ],
            'arg2'            => [
                'type'        => 'string',
                'description' => 'Description from ClassA',
            ],
        ]);
        $this->assertEquals($phpDoc->Return, [
            'type'        => '$this',
            'description' => 'Description from ClassC',
        ]);
    }
}
