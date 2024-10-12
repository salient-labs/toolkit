<?php declare(strict_types=1);

namespace Salient\Tests\Sli\Internal;

use Salient\Sli\Internal\TokenExtractor;
use Salient\Tests\Sli\SliTestCase;
use Salient\Utility\Get;
use Salient\Utility\Str;

/**
 * @covers \Salient\Sli\Internal\TokenExtractor
 */
final class TokenExtractorTest extends SliTestCase
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

    /**
     * @dataProvider getNamespacesProvider
     *
     * @param array<array{string,array<array{int,int,string,array{int|null,int|null},array{int|null,int|null},int|null,array{int|null,int|null}}>}> $expected
     */
    public function testGetNamespaces($expected, string $code): void
    {
        foreach ((new TokenExtractor(Str::eolFromNative($code)))->getNamespaces() as $namespace => $extractor) {
            [$tokens, $tokensCode] = self::serializeTokens($extractor->getTokens());
            $actual[] = [$namespace, $tokens];
            $actualCode[] = sprintf('[%s, %s],', Get::code($namespace), $tokensCode);
        }
        $actualCode = sprintf('[%s]', implode(' ', $actualCode ?? []));
        $this->assertSame(
            $expected,
            $actual ?? [],
            'If $code changed, replace $expected with: ' . $actualCode,
        );
    }

    /**
     * @return array<array{array<array{string,array<array{int,int,string,array{int|null,int|null},array{int|null,int|null},int|null,array{int|null,int|null}}>}>,string}>
     */
    public static function getNamespacesProvider(): array
    {
        return [
            'simple combination syntax' => [
                [
                    ['', [
                        [0, \T_OPEN_TAG, "<?php\n", [null, null], [null, null], null, [null, null]],
                    ]],
                    ['MyProject', [
                        [0, \T_CONST, 'const', [null, 1], [null, 1], null, [null, null]],
                        [1, \T_STRING, 'CONNECT_OK', [0, 2], [0, 2], null, [null, null]],
                        [2, 61, '=', [1, 3], [1, 3], null, [null, null]],
                        [3, \T_LNUMBER, '1', [2, 4], [2, 4], null, [null, null]],
                        [4, 59, ';', [3, 5], [3, 5], null, [null, null]],
                        [5, \T_CLASS, 'class', [4, 6], [4, 6], null, [null, null]],
                        [6, \T_STRING, 'Connection', [5, 7], [5, 7], null, [null, null]],
                        [7, 123, '{', [6, 8], [6, 9], null, [null, 9]],
                        [8, \T_COMMENT, '/* ... */', [7, 9], [7, 9], 7, [null, null]],
                        [9, 125, '}', [8, 10], [7, 10], null, [7, null]],
                        [10, \T_FUNCTION, 'function', [9, 11], [9, 11], null, [null, null]],
                        [11, \T_STRING, 'connect', [10, 12], [10, 12], null, [null, null]],
                        [12, 40, '(', [11, 13], [11, 13], null, [null, 13]],
                        [13, 41, ')', [12, 14], [12, 14], null, [12, null]],
                        [14, 123, '{', [13, 15], [13, 16], null, [null, 16]],
                        [15, \T_COMMENT, '/* ... */', [14, 16], [14, 16], 14, [null, null]],
                        [16, 125, '}', [15, null], [14, null], null, [14, null]],
                    ]],
                    ['AnotherProject', [
                        [0, \T_CONST, 'const', [null, 1], [null, 1], null, [null, null]],
                        [1, \T_STRING, 'CONNECT_OK', [0, 2], [0, 2], null, [null, null]],
                        [2, 61, '=', [1, 3], [1, 3], null, [null, null]],
                        [3, \T_LNUMBER, '1', [2, 4], [2, 4], null, [null, null]],
                        [4, 59, ';', [3, 5], [3, 5], null, [null, null]],
                        [5, \T_CLASS, 'class', [4, 6], [4, 6], null, [null, null]],
                        [6, \T_STRING, 'Connection', [5, 7], [5, 7], null, [null, null]],
                        [7, 123, '{', [6, 8], [6, 9], null, [null, 9]],
                        [8, \T_COMMENT, '/* ... */', [7, 9], [7, 9], 7, [null, null]],
                        [9, 125, '}', [8, 10], [7, 10], null, [7, null]],
                        [10, \T_FUNCTION, 'function', [9, 11], [9, 11], null, [null, null]],
                        [11, \T_STRING, 'connect', [10, 12], [10, 12], null, [null, null]],
                        [12, 40, '(', [11, 13], [11, 13], null, [null, 13]],
                        [13, 41, ')', [12, 14], [12, 14], null, [12, null]],
                        [14, 123, '{', [13, 15], [13, 16], null, [null, 16]],
                        [15, \T_COMMENT, '/* ... */', [14, 16], [14, 16], 14, [null, null]],
                        [16, 125, '}', [15, null], [14, null], null, [14, null]],
                    ]],
                ],
                // Based on:
                // https://www.php.net/manual/en/language.namespaces.definitionmultiple.php
                <<<'PHP'
<?php
namespace MyProject;

const CONNECT_OK = 1;
class Connection { /* ... */ }
function connect() { /* ... */ }

namespace AnotherProject;

const CONNECT_OK = 1;
class Connection { /* ... */ }
function connect() { /* ... */ }
PHP,
            ],
            'bracketed syntax' => [
                [
                    ['', [
                        [0, \T_OPEN_TAG, "<?php\n", [null, null], [null, null], null, [null, null]],
                    ]],
                    ['MyProject', [
                        [0, \T_CONST, 'const', [null, 1], [null, 1], null, [null, null]],
                        [1, \T_STRING, 'CONNECT_OK', [0, 2], [0, 2], null, [null, null]],
                        [2, 61, '=', [1, 3], [1, 3], null, [null, null]],
                        [3, \T_LNUMBER, '1', [2, 4], [2, 4], null, [null, null]],
                        [4, 59, ';', [3, 5], [3, 5], null, [null, null]],
                        [5, \T_CLASS, 'class', [4, 6], [4, 6], null, [null, null]],
                        [6, \T_STRING, 'Connection', [5, 7], [5, 7], null, [null, null]],
                        [7, 123, '{', [6, 8], [6, 9], null, [null, 9]],
                        [8, \T_COMMENT, '/* ... */', [7, 9], [7, 9], 7, [null, null]],
                        [9, 125, '}', [8, 10], [7, 10], null, [7, null]],
                        [10, \T_FUNCTION, 'function', [9, 11], [9, 11], null, [null, null]],
                        [11, \T_STRING, 'connect', [10, 12], [10, 12], null, [null, null]],
                        [12, 40, '(', [11, 13], [11, 13], null, [null, 13]],
                        [13, 41, ')', [12, 14], [12, 14], null, [12, null]],
                        [14, 123, '{', [13, 15], [13, 16], null, [null, 16]],
                        [15, \T_COMMENT, '/* ... */', [14, 16], [14, 16], 14, [null, null]],
                        [16, 125, '}', [15, null], [14, null], null, [14, null]],
                    ]],
                    ['AnotherProject', [
                        [0, \T_CONST, 'const', [null, 1], [null, 1], null, [null, null]],
                        [1, \T_STRING, 'CONNECT_OK', [0, 2], [0, 2], null, [null, null]],
                        [2, 61, '=', [1, 3], [1, 3], null, [null, null]],
                        [3, \T_LNUMBER, '1', [2, 4], [2, 4], null, [null, null]],
                        [4, 59, ';', [3, 5], [3, 5], null, [null, null]],
                        [5, \T_CLASS, 'class', [4, 6], [4, 6], null, [null, null]],
                        [6, \T_STRING, 'Connection', [5, 7], [5, 7], null, [null, null]],
                        [7, 123, '{', [6, 8], [6, 9], null, [null, 9]],
                        [8, \T_COMMENT, '/* ... */', [7, 9], [7, 9], 7, [null, null]],
                        [9, 125, '}', [8, 10], [7, 10], null, [7, null]],
                        [10, \T_FUNCTION, 'function', [9, 11], [9, 11], null, [null, null]],
                        [11, \T_STRING, 'connect', [10, 12], [10, 12], null, [null, null]],
                        [12, 40, '(', [11, 13], [11, 13], null, [null, 13]],
                        [13, 41, ')', [12, 14], [12, 14], null, [12, null]],
                        [14, 123, '{', [13, 15], [13, 16], null, [null, 16]],
                        [15, \T_COMMENT, '/* ... */', [14, 16], [14, 16], 14, [null, null]],
                        [16, 125, '}', [15, null], [14, null], null, [14, null]],
                    ]],
                ],
                // Based on:
                // https://www.php.net/manual/en/language.namespaces.definitionmultiple.php
                <<<'PHP'
<?php
namespace MyProject
{
    const CONNECT_OK = 1;
    class Connection { /* ... */ }
    function connect() { /* ... */ }
}

namespace AnotherProject
{
    const CONNECT_OK = 1;
    class Connection { /* ... */ }
    function connect() { /* ... */ }
}
PHP,
            ],
            'multiple namespaces and unnamespaced code' => [
                [
                    ['', [
                        [0, \T_OPEN_TAG, '<?php ', [null, 1], [null, 1], null, [null, null]],
                        [1, \T_DECLARE, 'declare', [0, 2], [null, 2], null, [null, null]],
                        [2, 40, '(', [1, 3], [1, 3], null, [null, 6]],
                        [3, \T_STRING, 'strict_types', [2, 4], [2, 4], 2, [null, null]],
                        [4, 61, '=', [3, 5], [3, 5], 2, [null, null]],
                        [5, \T_LNUMBER, '1', [4, 6], [4, 6], 2, [null, null]],
                        [6, 41, ')', [5, 7], [5, 7], null, [2, null]],
                        [7, 59, ';', [6, null], [6, null], null, [null, null]],
                    ]],
                    ['MyProject', [
                        [0, \T_CONST, 'const', [null, 1], [null, 1], null, [null, null]],
                        [1, \T_STRING, 'CONNECT_OK', [0, 2], [0, 2], null, [null, null]],
                        [2, 61, '=', [1, 3], [1, 3], null, [null, null]],
                        [3, \T_LNUMBER, '1', [2, 4], [2, 4], null, [null, null]],
                        [4, 59, ';', [3, 5], [3, 5], null, [null, null]],
                        [5, \T_CLASS, 'class', [4, 6], [4, 6], null, [null, null]],
                        [6, \T_STRING, 'Connection', [5, 7], [5, 7], null, [null, null]],
                        [7, 123, '{', [6, 8], [6, 9], null, [null, 9]],
                        [8, \T_COMMENT, '/* ... */', [7, 9], [7, 9], 7, [null, null]],
                        [9, 125, '}', [8, 10], [7, 10], null, [7, null]],
                        [10, \T_FUNCTION, 'function', [9, 11], [9, 11], null, [null, null]],
                        [11, \T_STRING, 'connect', [10, 12], [10, 12], null, [null, null]],
                        [12, 40, '(', [11, 13], [11, 13], null, [null, 13]],
                        [13, 41, ')', [12, 14], [12, 14], null, [12, null]],
                        [14, 123, '{', [13, 15], [13, 16], null, [null, 16]],
                        [15, \T_COMMENT, '/* ... */', [14, 16], [14, 16], 14, [null, null]],
                        [16, 125, '}', [15, null], [14, null], null, [14, null]],
                    ]],
                    ['', [
                        [0, \T_COMMENT, '// global code', [null, 1], [null, 1], null, [null, null]],
                        [1, \T_STRING, 'session_start', [0, 2], [null, 2], null, [null, null]],
                        [2, 40, '(', [1, 3], [1, 3], null, [null, 3]],
                        [3, 41, ')', [2, 4], [2, 4], null, [2, null]],
                        [4, 59, ';', [3, 5], [3, 5], null, [null, null]],
                        [5, \T_VARIABLE, '$a', [4, 6], [4, 6], null, [null, null]],
                        [6, 61, '=', [5, 7], [5, 7], null, [null, null]],
                        [7, \T_NAME_QUALIFIED, 'MyProject\connect', [6, 8], [6, 8], null, [null, null]],
                        [8, 40, '(', [7, 9], [7, 9], null, [null, 9]],
                        [9, 41, ')', [8, 10], [8, 10], null, [8, null]],
                        [10, 59, ';', [9, 11], [9, 11], null, [null, null]],
                        [11, \T_ECHO, 'echo', [10, 12], [10, 12], null, [null, null]],
                        [12, \T_NAME_QUALIFIED, 'MyProject\Connection', [11, 13], [11, 13], null, [null, null]],
                        [13, \T_DOUBLE_COLON, '::', [12, 14], [12, 14], null, [null, null]],
                        [14, \T_STRING, 'start', [13, 15], [13, 15], null, [null, null]],
                        [15, 40, '(', [14, 16], [14, 16], null, [null, 16]],
                        [16, 41, ')', [15, 17], [15, 17], null, [15, null]],
                        [17, 59, ';', [16, null], [16, null], null, [null, null]],
                    ]],
                ],
                // Based on:
                // https://www.php.net/manual/en/language.namespaces.definitionmultiple.php
                <<<'PHP'
<?php declare(strict_types=1);

namespace MyProject
{
    const CONNECT_OK = 1;
    class Connection { /* ... */ }
    function connect() { /* ... */ }
}

namespace
{  // global code
    session_start();
    $a = MyProject\connect();
    echo MyProject\Connection::start();
}
PHP,
            ],
        ];
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
