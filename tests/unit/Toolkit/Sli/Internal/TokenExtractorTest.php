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
            $this->assertSame($namespace, $extractor->getNamespace());
            $this->assertSame([$extractor], Get::list($extractor->getNamespaces()));
            $this->assertSame([$namespace => $extractor], Get::array($extractor->getNamespaces()));
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
            'empty' => [
                [],
                '',
            ],
            'simple combination syntax' => [
                [
                    ['', [
                        [0, \T_OPEN_TAG, "<?php\n", [null, 1], [null, 1], null, [null, null]],
                    ]],
                    ['MyProject', [
                        4 => [4, \T_CONST, 'const', [3, 5], [3, 5], null, [null, null]],
                        5 => [5, \T_STRING, 'CONNECT_OK', [4, 6], [4, 6], null, [null, null]],
                        6 => [6, 61, '=', [5, 7], [5, 7], null, [null, null]],
                        7 => [7, \T_LNUMBER, '1', [6, 8], [6, 8], null, [null, null]],
                        8 => [8, 59, ';', [7, 9], [7, 9], null, [null, null]],
                        9 => [9, \T_CLASS, 'class', [8, 10], [8, 10], null, [null, null]],
                        10 => [10, \T_STRING, 'Connection', [9, 11], [9, 11], null, [null, null]],
                        11 => [11, 123, '{', [10, 12], [10, 13], null, [null, 13]],
                        12 => [12, \T_COMMENT, '/* ... */', [11, 13], [11, 13], 11, [null, null]],
                        13 => [13, 125, '}', [12, 14], [11, 14], null, [11, null]],
                        14 => [14, \T_FUNCTION, 'function', [13, 15], [13, 15], null, [null, null]],
                        15 => [15, \T_STRING, 'connect', [14, 16], [14, 16], null, [null, null]],
                        16 => [16, 40, '(', [15, 17], [15, 17], null, [null, 17]],
                        17 => [17, 41, ')', [16, 18], [16, 18], null, [16, null]],
                        18 => [18, 123, '{', [17, 19], [17, 20], null, [null, 20]],
                        19 => [19, \T_COMMENT, '/* ... */', [18, 20], [18, 20], 18, [null, null]],
                        20 => [20, 125, '}', [19, 21], [18, 21], null, [18, null]],
                    ]],
                    ['AnotherProject', [
                        24 => [24, \T_CONST, 'const', [23, 25], [23, 25], null, [null, null]],
                        25 => [25, \T_STRING, 'CONNECT_OK', [24, 26], [24, 26], null, [null, null]],
                        26 => [26, 61, '=', [25, 27], [25, 27], null, [null, null]],
                        27 => [27, \T_LNUMBER, '1', [26, 28], [26, 28], null, [null, null]],
                        28 => [28, 59, ';', [27, 29], [27, 29], null, [null, null]],
                        29 => [29, \T_CLASS, 'class', [28, 30], [28, 30], null, [null, null]],
                        30 => [30, \T_STRING, 'Connection', [29, 31], [29, 31], null, [null, null]],
                        31 => [31, 123, '{', [30, 32], [30, 33], null, [null, 33]],
                        32 => [32, \T_COMMENT, '/* ... */', [31, 33], [31, 33], 31, [null, null]],
                        33 => [33, 125, '}', [32, 34], [31, 34], null, [31, null]],
                        34 => [34, \T_FUNCTION, 'function', [33, 35], [33, 35], null, [null, null]],
                        35 => [35, \T_STRING, 'connect', [34, 36], [34, 36], null, [null, null]],
                        36 => [36, 40, '(', [35, 37], [35, 37], null, [null, 37]],
                        37 => [37, 41, ')', [36, 38], [36, 38], null, [36, null]],
                        38 => [38, 123, '{', [37, 39], [37, 40], null, [null, 40]],
                        39 => [39, \T_COMMENT, '/* ... */', [38, 40], [38, 40], 38, [null, null]],
                        40 => [40, 125, '}', [39, null], [38, null], null, [38, null]],
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
                        [0, \T_OPEN_TAG, "<?php\n", [null, 1], [null, 1], null, [null, null]],
                    ]],
                    ['MyProject', [
                        4 => [4, \T_CONST, 'const', [3, 5], [3, 5], 3, [null, null]],
                        5 => [5, \T_STRING, 'CONNECT_OK', [4, 6], [4, 6], 3, [null, null]],
                        6 => [6, 61, '=', [5, 7], [5, 7], 3, [null, null]],
                        7 => [7, \T_LNUMBER, '1', [6, 8], [6, 8], 3, [null, null]],
                        8 => [8, 59, ';', [7, 9], [7, 9], 3, [null, null]],
                        9 => [9, \T_CLASS, 'class', [8, 10], [8, 10], 3, [null, null]],
                        10 => [10, \T_STRING, 'Connection', [9, 11], [9, 11], 3, [null, null]],
                        11 => [11, 123, '{', [10, 12], [10, 13], 3, [null, 13]],
                        12 => [12, \T_COMMENT, '/* ... */', [11, 13], [11, 13], 11, [null, null]],
                        13 => [13, 125, '}', [12, 14], [11, 14], 3, [11, null]],
                        14 => [14, \T_FUNCTION, 'function', [13, 15], [13, 15], 3, [null, null]],
                        15 => [15, \T_STRING, 'connect', [14, 16], [14, 16], 3, [null, null]],
                        16 => [16, 40, '(', [15, 17], [15, 17], 3, [null, 17]],
                        17 => [17, 41, ')', [16, 18], [16, 18], 3, [16, null]],
                        18 => [18, 123, '{', [17, 19], [17, 20], 3, [null, 20]],
                        19 => [19, \T_COMMENT, '/* ... */', [18, 20], [18, 20], 18, [null, null]],
                        20 => [20, 125, '}', [19, 21], [18, 21], 3, [18, null]],
                    ]],
                    ['AnotherProject', [
                        25 => [25, \T_CONST, 'const', [24, 26], [24, 26], 24, [null, null]],
                        26 => [26, \T_STRING, 'CONNECT_OK', [25, 27], [25, 27], 24, [null, null]],
                        27 => [27, 61, '=', [26, 28], [26, 28], 24, [null, null]],
                        28 => [28, \T_LNUMBER, '1', [27, 29], [27, 29], 24, [null, null]],
                        29 => [29, 59, ';', [28, 30], [28, 30], 24, [null, null]],
                        30 => [30, \T_CLASS, 'class', [29, 31], [29, 31], 24, [null, null]],
                        31 => [31, \T_STRING, 'Connection', [30, 32], [30, 32], 24, [null, null]],
                        32 => [32, 123, '{', [31, 33], [31, 34], 24, [null, 34]],
                        33 => [33, \T_COMMENT, '/* ... */', [32, 34], [32, 34], 32, [null, null]],
                        34 => [34, 125, '}', [33, 35], [32, 35], 24, [32, null]],
                        35 => [35, \T_FUNCTION, 'function', [34, 36], [34, 36], 24, [null, null]],
                        36 => [36, \T_STRING, 'connect', [35, 37], [35, 37], 24, [null, null]],
                        37 => [37, 40, '(', [36, 38], [36, 38], 24, [null, 38]],
                        38 => [38, 41, ')', [37, 39], [37, 39], 24, [37, null]],
                        39 => [39, 123, '{', [38, 40], [38, 41], 24, [null, 41]],
                        40 => [40, \T_COMMENT, '/* ... */', [39, 41], [39, 41], 39, [null, null]],
                        41 => [41, 125, '}', [40, 42], [39, 42], 24, [39, null]],
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
                \PHP_VERSION_ID < 80000 ? [
                    ['', [
                        [0, \T_OPEN_TAG, '<?php ', [null, 1], [null, 1], null, [null, null]],
                        [1, \T_DECLARE, 'declare', [0, 2], [null, 2], null, [null, null]],
                        [2, 40, '(', [1, 3], [1, 3], null, [null, 6]],
                        [3, \T_STRING, 'strict_types', [2, 4], [2, 4], 2, [null, null]],
                        [4, 61, '=', [3, 5], [3, 5], 2, [null, null]],
                        [5, \T_LNUMBER, '1', [4, 6], [4, 6], 2, [null, null]],
                        [6, 41, ')', [5, 7], [5, 7], null, [2, null]],
                        [7, 59, ';', [6, 8], [6, 8], null, [null, null]],
                    ]],
                    ['MyProject', [
                        11 => [11, \T_CONST, 'const', [10, 12], [10, 12], 10, [null, null]],
                        12 => [12, \T_STRING, 'CONNECT_OK', [11, 13], [11, 13], 10, [null, null]],
                        13 => [13, 61, '=', [12, 14], [12, 14], 10, [null, null]],
                        14 => [14, \T_LNUMBER, '1', [13, 15], [13, 15], 10, [null, null]],
                        15 => [15, 59, ';', [14, 16], [14, 16], 10, [null, null]],
                        16 => [16, \T_CLASS, 'class', [15, 17], [15, 17], 10, [null, null]],
                        17 => [17, \T_STRING, 'Connection', [16, 18], [16, 18], 10, [null, null]],
                        18 => [18, 123, '{', [17, 19], [17, 20], 10, [null, 20]],
                        19 => [19, \T_COMMENT, '/* ... */', [18, 20], [18, 20], 18, [null, null]],
                        20 => [20, 125, '}', [19, 21], [18, 21], 10, [18, null]],
                        21 => [21, \T_FUNCTION, 'function', [20, 22], [20, 22], 10, [null, null]],
                        22 => [22, \T_STRING, 'connect', [21, 23], [21, 23], 10, [null, null]],
                        23 => [23, 40, '(', [22, 24], [22, 24], 10, [null, 24]],
                        24 => [24, 41, ')', [23, 25], [23, 25], 10, [23, null]],
                        25 => [25, 123, '{', [24, 26], [24, 27], 10, [null, 27]],
                        26 => [26, \T_COMMENT, '/* ... */', [25, 27], [25, 27], 25, [null, null]],
                        27 => [27, 125, '}', [26, 28], [25, 28], 10, [25, null]],
                    ]],
                    ['', [
                        31 => [31, \T_COMMENT, '// global code', [30, 32], [30, 32], 30, [null, null]],
                        32 => [32, \T_STRING, 'session_start', [31, 33], [30, 33], 30, [null, null]],
                        33 => [33, 40, '(', [32, 34], [32, 34], 30, [null, 34]],
                        34 => [34, 41, ')', [33, 35], [33, 35], 30, [33, null]],
                        35 => [35, 59, ';', [34, 36], [34, 36], 30, [null, null]],
                        36 => [36, \T_VARIABLE, '$a', [35, 37], [35, 37], 30, [null, null]],
                        37 => [37, 61, '=', [36, 38], [36, 38], 30, [null, null]],
                        38 => [38, \T_STRING, 'MyProject', [37, 39], [37, 39], 30, [null, null]],
                        39 => [39, \T_NS_SEPARATOR, '\\', [38, 40], [38, 40], 30, [null, null]],
                        40 => [40, \T_STRING, 'connect', [39, 41], [39, 41], 30, [null, null]],
                        41 => [41, 40, '(', [40, 42], [40, 42], 30, [null, 42]],
                        42 => [42, 41, ')', [41, 43], [41, 43], 30, [41, null]],
                        43 => [43, 59, ';', [42, 44], [42, 44], 30, [null, null]],
                        44 => [44, \T_ECHO, 'echo', [43, 45], [43, 45], 30, [null, null]],
                        45 => [45, \T_STRING, 'MyProject', [44, 46], [44, 46], 30, [null, null]],
                        46 => [46, \T_NS_SEPARATOR, '\\', [45, 47], [45, 47], 30, [null, null]],
                        47 => [47, \T_STRING, 'Connection', [46, 48], [46, 48], 30, [null, null]],
                        48 => [48, \T_DOUBLE_COLON, '::', [47, 49], [47, 49], 30, [null, null]],
                        49 => [49, \T_STRING, 'start', [48, 50], [48, 50], 30, [null, null]],
                        50 => [50, 40, '(', [49, 51], [49, 51], 30, [null, 51]],
                        51 => [51, 41, ')', [50, 52], [50, 52], 30, [50, null]],
                        52 => [52, 59, ';', [51, 53], [51, 53], 30, [null, null]],
                    ]],
                ] : [
                    ['', [
                        [0, \T_OPEN_TAG, '<?php ', [null, 1], [null, 1], null, [null, null]],
                        [1, \T_DECLARE, 'declare', [0, 2], [null, 2], null, [null, null]],
                        [2, 40, '(', [1, 3], [1, 3], null, [null, 6]],
                        [3, \T_STRING, 'strict_types', [2, 4], [2, 4], 2, [null, null]],
                        [4, 61, '=', [3, 5], [3, 5], 2, [null, null]],
                        [5, \T_LNUMBER, '1', [4, 6], [4, 6], 2, [null, null]],
                        [6, 41, ')', [5, 7], [5, 7], null, [2, null]],
                        [7, 59, ';', [6, 8], [6, 8], null, [null, null]],
                    ]],
                    ['MyProject', [
                        11 => [11, \T_CONST, 'const', [10, 12], [10, 12], 10, [null, null]],
                        12 => [12, \T_STRING, 'CONNECT_OK', [11, 13], [11, 13], 10, [null, null]],
                        13 => [13, 61, '=', [12, 14], [12, 14], 10, [null, null]],
                        14 => [14, \T_LNUMBER, '1', [13, 15], [13, 15], 10, [null, null]],
                        15 => [15, 59, ';', [14, 16], [14, 16], 10, [null, null]],
                        16 => [16, \T_CLASS, 'class', [15, 17], [15, 17], 10, [null, null]],
                        17 => [17, \T_STRING, 'Connection', [16, 18], [16, 18], 10, [null, null]],
                        18 => [18, 123, '{', [17, 19], [17, 20], 10, [null, 20]],
                        19 => [19, \T_COMMENT, '/* ... */', [18, 20], [18, 20], 18, [null, null]],
                        20 => [20, 125, '}', [19, 21], [18, 21], 10, [18, null]],
                        21 => [21, \T_FUNCTION, 'function', [20, 22], [20, 22], 10, [null, null]],
                        22 => [22, \T_STRING, 'connect', [21, 23], [21, 23], 10, [null, null]],
                        23 => [23, 40, '(', [22, 24], [22, 24], 10, [null, 24]],
                        24 => [24, 41, ')', [23, 25], [23, 25], 10, [23, null]],
                        25 => [25, 123, '{', [24, 26], [24, 27], 10, [null, 27]],
                        26 => [26, \T_COMMENT, '/* ... */', [25, 27], [25, 27], 25, [null, null]],
                        27 => [27, 125, '}', [26, 28], [25, 28], 10, [25, null]],
                    ]],
                    ['', [
                        31 => [31, \T_COMMENT, '// global code', [30, 32], [30, 32], 30, [null, null]],
                        32 => [32, \T_STRING, 'session_start', [31, 33], [30, 33], 30, [null, null]],
                        33 => [33, 40, '(', [32, 34], [32, 34], 30, [null, 34]],
                        34 => [34, 41, ')', [33, 35], [33, 35], 30, [33, null]],
                        35 => [35, 59, ';', [34, 36], [34, 36], 30, [null, null]],
                        36 => [36, \T_VARIABLE, '$a', [35, 37], [35, 37], 30, [null, null]],
                        37 => [37, 61, '=', [36, 38], [36, 38], 30, [null, null]],
                        38 => [38, \T_NAME_QUALIFIED, 'MyProject\connect', [37, 39], [37, 39], 30, [null, null]],
                        39 => [39, 40, '(', [38, 40], [38, 40], 30, [null, 40]],
                        40 => [40, 41, ')', [39, 41], [39, 41], 30, [39, null]],
                        41 => [41, 59, ';', [40, 42], [40, 42], 30, [null, null]],
                        42 => [42, \T_ECHO, 'echo', [41, 43], [41, 43], 30, [null, null]],
                        43 => [43, \T_NAME_QUALIFIED, 'MyProject\Connection', [42, 44], [42, 44], 30, [null, null]],
                        44 => [44, \T_DOUBLE_COLON, '::', [43, 45], [43, 45], 30, [null, null]],
                        45 => [45, \T_STRING, 'start', [44, 46], [44, 46], 30, [null, null]],
                        46 => [46, 40, '(', [45, 47], [45, 47], 30, [null, 47]],
                        47 => [47, 41, ')', [46, 48], [46, 48], 30, [46, null]],
                        48 => [48, 59, ';', [47, 49], [47, 49], 30, [null, null]],
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

    public function testGenerators(): void
    {
        $tokensCode = null;
        foreach ((new TokenExtractor(
            <<<'PHP'
<?php
namespace Foo
{
    interface A { public function getFoo(): int; }
    interface B {}
}
namespace Bar\Baz
{
    interface C extends \Foo\A, \foo\b {}
    interface D {}
    interface E {}
    interface EE extends D, e { public function &getBaz(): int; }
}
namespace Bar
{
    abstract class F implements namespace\Baz\C, namespace\baz\d {}
    class FF extends F { public function getFoo(): int { return -1; } }
}
namespace
{
    class G extends \Bar\FF implements namespace\Bar\Baz\E {}
}
namespace
{
    use Bar\Baz\D as Foo;
    use \Foo\A;
    class H implements A, foo
    {
        public int $Foo;
        public function getFoo(): int { return $this->Foo; }
        protected static function bar(): void { (function &() {})(); }
    }
    class HH extends H {}
}
PHP,
        ))->getNamespaces() as $namespace => $extractor) {
            foreach ([
                \T_CLASS => $extractor->getClasses(),
                \T_INTERFACE => $extractor->getInterfaces(),
                \T_TRAIT => $extractor->getTraits(),
                \T_ENUM => $extractor->getEnums(),
            ] as $id => $classes) {
                foreach ($classes as $class => $classExtractor) {
                    $this->assertSame($extractor, $classExtractor->getParent());
                    $this->assertTrue($classExtractor->hasClass());
                    $this->assertSame($class, $classExtractor->getClass());
                    $this->assertNotNull($token = $classExtractor->getClassToken());
                    $this->assertSame($id, $token->id);
                    $this->assertEmpty(Get::array($classExtractor->getClasses()));
                    $this->assertNull($extractor->getName($token));
                    if (
                        ($token = $token->NextCode)
                        && ($token = $token->NextCode)
                    ) {
                        while (
                            ($token->id === \T_EXTENDS || $token->id === \T_COMMA)
                            && ($token = $token->NextCode)
                        ) {
                            $actual[$namespace][$class]['extends'][] = $extractor->getName($token, $token);
                        }
                        while (
                            $token
                            && ($token->id === \T_IMPLEMENTS || $token->id === \T_COMMA)
                            && ($token = $token->NextCode)
                        ) {
                            $actual[$namespace][$class]['implements'][] = $extractor->getName($token, $token);
                        }
                    }
                    foreach ($classExtractor->getFunctions() as $function => $functionExtractor) {
                        $this->assertSame($classExtractor, $functionExtractor->getParent());
                        $this->assertTrue($functionExtractor->hasMember());
                        $this->assertSame($function, $functionExtractor->getMember());
                        $this->assertNotNull($token = $functionExtractor->getMemberToken());
                        $this->assertSame(\T_FUNCTION, $token->id);
                        $this->assertEmpty(Get::array($functionExtractor->getFunctions()));
                        [$tokens[$namespace][$class][$function]] = self::serializeTokens(
                            $functionExtractor->getTokens(),
                            // @phpstan-ignore offsetAccess.notFound
                            $tokensCode[$namespace][$class][$function],
                            $constants,
                        );
                    }
                }
            }
        }

        $actualCode = Get::code($actual ?? []);
        $this->assertSame(
            [
                'Bar\Baz' => [
                    'C' => ['extends' => ['Foo\A', 'foo\b']],
                    'EE' => ['extends' => ['Bar\Baz\D', 'Bar\Baz\e']],
                ],
                'Bar' => [
                    'F' => ['implements' => ['Bar\Baz\C', 'Bar\baz\d']],
                    'FF' => ['extends' => ['Bar\F']],
                ],
                '' => [
                    'G' => ['extends' => ['Bar\FF'], 'implements' => ['Bar\Baz\E']],
                    'H' => ['implements' => ['Foo\A', 'Bar\Baz\D']],
                    'HH' => ['extends' => ['H']],
                ],
            ],
            $actual ?? [],
            'If code changed, replace $expected with: ' . $actualCode,
        );

        $tokensCode = Get::code(
            // @phpstan-ignore nullCoalesce.variable
            $tokensCode ?? [],
            ', ',
            ' => ',
            null,
            '    ',
            [],
            $constants ?? [],
        );
        $this->assertSame(
            [
                'Foo' => [
                    'A' => [
                        'getFoo' => [],
                    ],
                ],
                'Bar\Baz' => [
                    'EE' => [
                        'getBaz' => [],
                    ],
                ],
                'Bar' => [
                    'FF' => [
                        'getFoo' => [
                            83 => [83, \T_RETURN, 'return', [82, 84], [82, 84], 82, [null, null]],
                            84 => [84, 45, '-', [83, 85], [83, 85], 82, [null, null]],
                            85 => [85, \T_LNUMBER, '1', [84, 86], [84, 86], 82, [null, null]],
                            86 => [86, 59, ';', [85, 87], [85, 87], 82, [null, null]],
                        ],
                    ],
                ],
                '' => [
                    'H' => [
                        'getFoo' => [
                            130 => [130, \T_RETURN, 'return', [129, 131], [129, 131], 129, [null, null]],
                            131 => [131, \T_VARIABLE, '$this', [130, 132], [130, 132], 129, [null, null]],
                            132 => [132, \T_OBJECT_OPERATOR, '->', [131, 133], [131, 133], 129, [null, null]],
                            133 => [133, \T_STRING, 'Foo', [132, 134], [132, 134], 129, [null, null]],
                            134 => [134, 59, ';', [133, 135], [133, 135], 129, [null, null]],
                        ],
                        'bar' => [
                            145 => [145, 40, '(', [144, 146], [144, 146], 144, [null, 152]],
                            146 => [146, \T_FUNCTION, 'function', [145, 147], [145, 147], 145, [null, null]],
                            147 => [147, \PHP_VERSION_ID < 80100 ? \T_AND : \T_AMPERSAND_NOT_FOLLOWED_BY_VAR_OR_VARARG, '&', [146, 148], [146, 148], 145, [null, null]],
                            148 => [148, 40, '(', [147, 149], [147, 149], 145, [null, 149]],
                            149 => [149, 41, ')', [148, 150], [148, 150], 145, [148, null]],
                            150 => [150, 123, '{', [149, 151], [149, 151], 145, [null, 151]],
                            151 => [151, 125, '}', [150, 152], [150, 152], 145, [150, null]],
                            152 => [152, 41, ')', [151, 153], [151, 153], 144, [145, null]],
                            153 => [153, 40, '(', [152, 154], [152, 154], 144, [null, 154]],
                            154 => [154, 41, ')', [153, 155], [153, 155], 144, [153, null]],
                            155 => [155, 59, ';', [154, 156], [154, 156], 144, [null, null]],
                        ],
                    ],
                ],
            ],
            $tokens ?? [],
            'If code changed, replace $expected with: ' . $tokensCode,
        );
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
