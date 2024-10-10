<?php declare(strict_types=1);

namespace Salient\Tests\Sli\Internal;

use Salient\Sli\Internal\TokenExtractor;
use Salient\Tests\TestCase;
use Salient\Utility\Get;

/**
 * @covers \Salient\Sli\Internal\TokenExtractor
 */
final class TokenExtractorTest extends TestCase
{
    public function testFromFile(): void
    {
        $extractor = TokenExtractor::fromFile(__FILE__);
        $this->assertInstanceOf(TokenExtractor::class, $extractor);
        foreach ($extractor->getTokens() as $token) {
            if ($token->line === __LINE__ && $token->id === \T_LINE) {
                return;
            }
        }
        $this->fail('Token not found');
    }

    public function testGetImports(): void
    {
        $extractor = new TokenExtractor(
            // Based on:
            // https://www.php.net/manual/en/language.namespaces.importing.php
            <<<'PHP'
<?php

use ArrayObject;
use function My\Full\functionName;
use function My\Full\functionName as func;
use const My\Full\CONSTANT;
use My\Full\Classname as Another, My\Full\NSname;
use some\_namespace\{ClassA, ClassB, ClassC as C};
use function some\_namespace\{fn_a, fn_b, fn_c};
use const some\_namespace\{ConstA, ConstB, ConstC};

class Foo
{
    use Bar;
}

function () use (&$baz) {
    return $baz++;
};

PHP,
        );

        $this->assertSame([
            'ArrayObject' => [\T_CLASS, 'ArrayObject'],
            'functionName' => [\T_FUNCTION, 'My\Full\functionName'],
            'func' => [\T_FUNCTION, 'My\Full\functionName'],
            'CONSTANT' => [\T_CONST, 'My\Full\CONSTANT'],
            'Another' => [\T_CLASS, 'My\Full\Classname'],
            'NSname' => [\T_CLASS, 'My\Full\NSname'],
            'ClassA' => [\T_CLASS, 'some\_namespace\ClassA'],
            'ClassB' => [\T_CLASS, 'some\_namespace\ClassB'],
            'C' => [\T_CLASS, 'some\_namespace\ClassC'],
            'fn_a' => [\T_FUNCTION, 'some\_namespace\fn_a'],
            'fn_b' => [\T_FUNCTION, 'some\_namespace\fn_b'],
            'fn_c' => [\T_FUNCTION, 'some\_namespace\fn_c'],
            'ConstA' => [\T_CONST, 'some\_namespace\ConstA'],
            'ConstB' => [\T_CONST, 'some\_namespace\ConstB'],
            'ConstC' => [\T_CONST, 'some\_namespace\ConstC'],
        ], Get::array($extractor->getImports()));
    }
}
