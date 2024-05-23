<?php declare(strict_types=1);

namespace Salient\Tests\Core\Utility\Get;

use Salient\Container\Container;
use Salient\Contract\Container\ContainerInterface;
use Salient\Contract\Container\HasContextualBindings;
use Salient\Contract\Container\HasServices;
use Salient\Contract\Container\SingletonInterface;
use Salient\Contract\Core\Arrayable;
use Salient\Contract\Core\CopyFlag;
use Salient\Core\Exception\UncloneableObjectException;
use Salient\Core\Utility\File;
use Salient\Core\Utility\Get;
use Salient\Tests\TestCase;
use ArrayIterator;
use ArrayObject;
use Closure;
use Countable;
use DateTimeImmutable;
use InvalidArgumentException;
use stdClass;
use Traversable;

/**
 * @covers \Salient\Core\Utility\Get
 */
final class GetTest extends TestCase
{
    /**
     * @dataProvider notNullProvider
     *
     * @param mixed $value
     */
    public function testNotNull($value): void
    {
        $this->assertSame($value, Get::notNull($value));
    }

    /**
     * @return array<array{mixed}>
     */
    public function notNullProvider(): array
    {
        return [
            [0],
            [1],
            [''],
            ['foo'],
            [[]],
            [['foo']],
            [new stdClass()],
        ];
    }

    public function testNotNullWithNull(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('$value cannot be null');
        Get::notNull(null);
    }

    /**
     * @dataProvider booleanProvider
     *
     * @param mixed $value
     */
    public function testBoolean(?bool $expected, $value): void
    {
        $this->assertSame($expected, Get::boolean($value));
    }

    /**
     * @return array<string,array{bool|null,mixed}>
     */
    public static function booleanProvider(): array
    {
        return [
            'null' => [null, null],
            'false' => [false, false],
            'true' => [true, true],
            '(int) 0' => [false, 0],
            '(int) 1' => [true, 1],
            "''" => [false, ''],
            "'0'" => [false, '0'],
            "'1'" => [true, '1'],
            "'f'" => [true, 'f'],
            "'false'" => [false, 'false'],
            "'n'" => [false, 'n'],
            "'no'" => [false, 'no'],
            "'off'" => [false, 'off'],
            "'on'" => [true, 'on'],
            "'t'" => [true, 't'],
            "'true'" => [true, 'true'],
            "'y'" => [true, 'y'],
            "'yes'" => [true, 'yes'],
        ];
    }

    /**
     * @dataProvider integerProvider
     *
     * @param int|float|string|bool|null $value
     */
    public function testInteger(?int $expected, $value): void
    {
        $this->assertSame($expected, Get::integer($value));
    }

    /**
     * @return array<string,array{int|null,int|float|string|bool|null}>
     */
    public static function integerProvider(): array
    {
        return [
            'null' => [null, null],
            'false' => [0, false],
            'true' => [1, true],
            '(int) 5' => [5, 5],
            '(float) 5.5' => [5, 5.5],
            "'5'" => [5, '5'],
            "'5.5'" => [5, '5.5'],
            "'foo'" => [0, 'foo'],
        ];
    }

    /**
     * @dataProvider arrayKeyProvider
     *
     * @param int|string|null $expected
     * @param int|string|null $value
     */
    public function testArrayKey($expected, $value): void
    {
        $this->assertSame($expected, Get::arrayKey($value));
    }

    /**
     * @return array<array{int|string|null,int|string|null}>
     */
    public static function arrayKeyProvider(): array
    {
        return [
            [null, null],
            ['', ''],
            [0, 0],
            [1, 1],
            ['null', 'null'],
            [' NULL ', ' NULL '],
            [0, '0'],
            [0, ' 0 '],
            [1, '1'],
            [1, ' 1 '],
            [-1, '-1'],
            [-1, ' -1 '],
            ['3.14', '3.14'],
            [' 3.14 ', ' 3.14 '],
            ['false', 'false'],
            [' no ', ' no '],
            ['true', 'true'],
            [' YES ', ' YES '],
        ];
    }

    public function testArrayKeyWithInvalidValue(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Argument #1 ($value) must be of type int|string|null, stdClass given');
        // @phpstan-ignore-next-line
        Get::arrayKey(new stdClass());
    }

    public function testClosure(): void
    {
        $this->assertNull(Get::closure(null));

        $closure = fn() => null;
        $this->assertSame($closure, Get::closure($closure));

        $callable = 'strtoupper';
        $closure = Get::closure($callable);
        $this->assertInstanceOf(Closure::class, $closure);
        $this->assertSame('FOO', $callable('foo'));
        $this->assertSame('BAR', $closure('bar'));
    }

    /**
     * @dataProvider valueProvider
     *
     * @param mixed $expected
     * @param mixed $value
     * @param mixed ...$args
     */
    public function testValue($expected, $value, ...$args): void
    {
        $this->assertSame($expected, Get::value($value, ...$args));
    }

    /**
     * @return array<array{mixed,mixed,...}>
     */
    public static function valueProvider(): array
    {
        return [
            [
                null,
                null,
            ],
            [
                0,
                0,
            ],
            [
                '',
                '',
            ],
            [
                'foo',
                'foo',
            ],
            [
                null,
                fn() => null,
            ],
            [
                0,
                fn() => 0,
            ],
            [
                '',
                fn() => '',
            ],
            [
                'foo',
                fn() => 'foo',
            ],
            [
                [],
                fn(...$args) => $args,
            ],
            [
                [null],
                fn(...$args) => $args,
                null,
            ],
            [
                [0],
                fn(...$args) => $args,
                0,
            ],
            [
                [''],
                fn(...$args) => $args,
                ''
            ],
            [
                ['foo'],
                fn(...$args) => $args,
                'foo',
            ],
            [
                [null, 0, '', 'foo'],
                fn(...$args) => $args,
                null,
                0,
                '',
                'foo',
            ],
        ];
    }

    /**
     * @dataProvider filterProvider
     *
     * @param mixed[]|string $expected
     * @param string[] $values
     */
    public function testFilter($expected, array $values, bool $discardInvalid = true): void
    {
        $this->maybeExpectException($expected);
        $this->assertSame($expected, Get::filter($values, $discardInvalid));
    }

    /**
     * @return array<array{mixed[]|string,string[],2?:bool}>
     */
    public static function filterProvider(): array
    {
        $maxInputVars = (int) ini_get('max_input_vars');
        /** @var string[] */
        $maxValues = array_fill(0, $maxInputVars, 'v=');
        /** @var string[] */
        $tooManyValues = array_fill(0, $maxInputVars + 1, 'v=');

        return [
            [
                [
                    'value1',
                    'value2',
                    '',
                    '',
                ],
                [
                    '0=value1',
                    '1=value2',
                    '2=value3',
                    '2=',
                    '3',
                    '=value5',
                ],
            ],
            [
                [
                    'key1' => 'value1',
                    'key2' => 'value2',
                    'key3' => '',
                    'key4' => '',
                ],
                [
                    'key1=value1',
                    'key2=value2',
                    'key3=value3',
                    'key3=',
                    'key4',
                    '=value5',
                ],
            ],
            [
                InvalidArgumentException::class . ",Invalid key-value pair: '=value'",
                ['=value'],
                false,
            ],
            [
                InvalidArgumentException::class . ",Invalid key-value pairs: '=value', ''",
                ['=value', ''],
                false,
            ],
            [
                [
                    'key' => [
                        0 => 'value1',
                        1 => 'value2',
                        3 => '',
                        4 => '',
                    ],
                ],
                [
                    'key[0]=value1',
                    'key[1]=value2',
                    'key[3]=value3',
                    'key[3]=',
                    'key[4]',
                ],
            ],
            [
                [
                    'where' => [
                        '__' => 'AND',
                        'ItemKey = "foo"',
                        [
                            '__' => 'OR',
                            'Expiry IS NULL',
                            'Expiry > "2024-03-21 16:51:35"',
                        ],
                    ],
                ],
                [
                    'where[__]=AND',
                    'where[0]=ItemKey = "foo"',
                    'where[1][__]=OR',
                    'where[1][0]=Expiry IS NULL',
                    'where[1][1]=Expiry > "2024-03-21 16:51:35"',
                ],
            ],
            [
                ['v' => ''],
                $maxValues,
            ],
            [
                InvalidArgumentException::class . ',Key-value pairs exceed max_input_vars',
                $tooManyValues,
            ],
        ];
    }

    /**
     * @dataProvider coalesceProvider
     *
     * @param mixed $expected
     * @param mixed ...$values
     */
    public function testCoalesce($expected, ...$values): void
    {
        $this->assertSame($expected, Get::coalesce(...$values));
    }

    /**
     * @return array<array{mixed,...}>
     */
    public static function coalesceProvider(): array
    {
        return [
            [null],
            [null, null],
            [0, null, 0],
            [0, 0, null],
            [0, null, 0, null],
            [false, null, false],
            [false, false, null],
            [false, null, false, null],
            ['', null, ''],
            ['', '', null],
            ['', null, '', null],
            ['', '', 'foo'],
            ['foo', 'foo', ''],
            ['foo', null, 'foo'],
            ['foo', 'foo', null],
            ['foo', null, 'foo', null],
            [[], [], null],
            [[null], [null], null],
            [['foo'], ['foo'], null],
        ];
    }

    /**
     * @dataProvider countProvider
     *
     * @param Traversable<array-key,mixed>|Arrayable<array-key,mixed>|Countable|array<array-key,mixed>|int $value
     */
    public function testCount(int $expected, $value): void
    {
        $this->assertSame($expected, Get::count($value));
    }

    /**
     * @return array<string,array{int,Traversable<array-key,mixed>|Arrayable<array-key,mixed>|Countable|array<array-key,mixed>|int}>
     */
    public static function countProvider(): array
    {
        return [
            'integer' => [
                5,
                5,
            ],
            'array' => [
                3,
                ['a', 'b', 'c'],
            ],
            'Countable' => [
                2,
                new ArrayObject(['x', 'y']),
            ],
            'Arrayable' => [
                4,
                new class implements Arrayable {
                    public function toArray(): array
                    {
                        return ['i', 'j', 'k', 'l'];
                    }
                },
            ],
            'Traversable' => [
                2,
                new ArrayIterator(['m', 'n']),
            ],
        ];
    }

    /**
     * @dataProvider basenameProvider
     */
    public function testBasename(string $expected, string $class, string ...$suffixes): void
    {
        $this->assertSame($expected, Get::basename($class, ...$suffixes));
    }

    /**
     * @return array<string[]>
     */
    public static function basenameProvider(): array
    {
        return [
            [
                'AcmeSyncProvider',
                'Acme\Sync\Provider\AcmeSyncProvider',
            ],
            [
                'AcmeSyncProvider',
                'Acme\Sync\Provider\AcmeSyncProvider',
                'Sync',
            ],
            [
                'Acme',
                'Acme\Sync\Provider\AcmeSyncProvider',
                'SyncProvider',
                'Provider',
            ],
            [
                'AcmeSync',
                'Acme\Sync\Provider\AcmeSyncProvider',
                'Provider',
                'SyncProvider',
            ],
            [
                'AcmeSync',
                'Acme\Sync\Provider\AcmeSyncProvider',
                'Provider',
                'SyncProvider',
            ],
            [
                'AcmeSyncProvider',
                'AcmeSyncProvider',
            ],
            [
                'AcmeSyncProvider',
                'AcmeSyncProvider',
                'AcmeSyncProvider',
            ],
            [
                'Acme',
                'AcmeSyncProvider',
                'AcmeSyncProvider',
                'SyncProvider',
            ],
        ];
    }

    /**
     * @dataProvider namespaceProvider
     */
    public function testNamespace(string $expected, string $class): void
    {
        $this->assertSame($expected, Get::namespace($class));
    }

    /**
     * @return array<string[]>
     */
    public static function namespaceProvider(): array
    {
        return [
            [
                'Acme\Sync\Provider',
                'Acme\Sync\Provider\AcmeSyncProvider',
            ],
            [
                'Acme\Sync\Provider',
                '\Acme\Sync\Provider\AcmeSyncProvider',
            ],
            [
                '',
                'AcmeSyncProvider',
            ],
            [
                '',
                '\AcmeSyncProvider',
            ],
        ];
    }

    /**
     * @dataProvider fqcnProvider
     *
     * @param class-string $class
     */
    public function testFqcn(string $expected, string $class): void
    {
        $this->assertSame($expected, Get::fqcn($class));
    }

    /**
     * @return array<string[]>
     */
    public static function fqcnProvider(): array
    {
        return [
            [
                'acme\sync\provider',
                'Acme\Sync\Provider',
            ],
            [
                'acme\sync\provider',
                '\Acme\Sync\Provider',
            ],
            [
                'acmesyncprovider',
                'AcmeSyncProvider',
            ],
            [
                'acmesyncprovider',
                '\AcmeSyncProvider',
            ],
        ];
    }

    /**
     * @dataProvider typeProvider
     *
     * @param mixed $value
     */
    public function testType(string $expected, $value): void
    {
        $this->assertSame($expected, Get::type($value));
    }

    /**
     * @return array<array{string,mixed}>
     */
    public static function typeProvider(): array
    {
        $f = File::open(__FILE__, 'r');
        File::close($f);

        return [
            ['null', null],
            ['bool', true],
            ['bool', false],
            ['int', 0],
            ['int', 1],
            ['float', 0.0],
            ['float', 3.14],
            ['string', ''],
            ['string', 'text'],
            ['string', '0'],
            ['array', []],
            ['array', ['foo', 'bar']],
            ['resource (closed)', $f],
            [stdClass::class, new stdClass()],
            ['class@anonymous', new class {}],
        ];
    }

    public function testTypeWithResource(): void
    {
        $f = File::open(__FILE__, 'r');
        $this->assertSame('resource (stream)', Get::type($f));
        File::close($f);
    }

    /**
     * @dataProvider bytesProvider
     */
    public function testBytes(int $expected, string $size): void
    {
        $this->assertSame($expected, Get::bytes($size));
    }

    /**
     * @return array<array{int,string}>
     */
    public static function bytesProvider(): array
    {
        return [
            [-1, '-1'],
            [0, ''],
            [0, '.5'],
            [0, '.5M'],
            [0, '0.5'],
            [0, '0.5M'],
            [1024, '1K'],
            [1048576, '1M'],
            [1048576, '1.5M'],
            [1048576, ' 1 M '],
            [1048576, ' 1.5 M '],
            [134217728, '128M'],
            [2147483648, '2G'],
        ];
    }

    /**
     * @dataProvider codeProvider
     *
     * @param mixed $value
     * @param string[] $classes
     * @param array<non-empty-string,string> $constants
     */
    public function testCode(
        string $expected,
        $value,
        array $classes = [],
        array $constants = [],
        string $delimiter = ', ',
        string $arrow = ' => ',
        ?string $escapeCharacters = null,
        string $tab = '    '
    ): void {
        $this->assertSame($expected, Get::code($value, $delimiter, $arrow, $escapeCharacters, $tab, $classes, $constants));
        $this->assertSame(eval("return $expected;"), $value);
    }

    /**
     * @return array<string,array{string,mixed,2?:string[],3?:array<non-empty-string,string>,4?:string,5?:string,6?:string|null,7?:string}>
     */
    public static function codeProvider(): array
    {
        $eol = \PHP_EOL;
        $esc = addcslashes(\PHP_EOL, "\n\r");
        $array = [
            'list1' => [1],
            'list2' => [1, 3.14, 6.626e-34],
            'list3' => ['foo' => 1, 2.0, 'bar' => 3, 4],
            'empty' => [],
            'index' => [5 => true, 2 => false],
            'multiline' . \PHP_EOL . 'key' => 'This string has "double quotes", \'single quotes\', and commas.',
            'classes' => [static::class, Get::basename(static::class), 'gettest'],
            'This string has line 1,' . \PHP_EOL . 'line 2, and no more lines.',
            '\\Vendor\\Namespace\\',
            "\xa0",
            '👩🏼‍🚒',
        ];
        $classes = [static::class, Get::basename(static::class)];
        $constants = [\PHP_EOL => '\PHP_EOL'];

        return [
            'default' => [
                <<<EOF
['list1' => [1], 'list2' => [1, 3.14, 6.626e-34], 'list3' => ['foo' => 1, 2.0, 'bar' => 3, 4], 'empty' => [], 'index' => [5 => true, 2 => false], "multiline{$esc}key" => 'This string has "double quotes", \'single quotes\', and commas.', 'classes' => [Salient\Tests\Core\Utility\Get\GetTest::class, GetTest::class, 'gettest'], "This string has line 1,{$esc}line 2, and no more lines.", '\\\\Vendor\\\\Namespace\\\\', "\\xa0", '👩🏼‍🚒']
EOF,
                $array,
                $classes,
            ],
            'compact' => [
                <<<EOF
['list1'=>[1],'list2'=>[1,3.14,6.626e-34],'list3'=>['foo'=>1,2.0,'bar'=>3,4],'empty'=>[],'index'=>[5=>true,2=>false],"multiline{$esc}key"=>'This string has "double quotes", \'single quotes\', and commas.','classes'=>[Salient\Tests\Core\Utility\Get\GetTest::class,GetTest::class,'gettest'],"This string has line 1,{$esc}line 2, and no more lines.",'\\\\Vendor\\\\Namespace\\\\',"\\xa0",'👩🏼‍🚒']
EOF,
                $array,
                $classes,
                [],
                ',',
                '=>',
            ],
            'multiline' => [
                <<<EOF
[
    'list1' => [
        1,
    ],
    'list2' => [
        1,
        3.14,
        6.626e-34,
    ],
    'list3' => [
        'foo' => 1,
        2.0,
        'bar' => 3,
        4,
    ],
    'empty' => [],
    'index' => [
        5 => true,
        2 => false,
    ],
    'multiline{$eol}key' => 'This string has "double quotes", \'single quotes\', and commas.',
    'classes' => [
        Salient\Tests\Core\Utility\Get\GetTest::class,
        GetTest::class,
        'gettest',
    ],
    'This string has line 1,{$eol}line 2, and no more lines.',
    '\\\\Vendor\\\\Namespace\\\\',
    "\\xa0",
    '👩🏼‍🚒',
]
EOF,
                $array,
                $classes,
                [],
                ',' . \PHP_EOL,
            ],
            'escaped commas' => [
                <<<EOF
['list1' => [1], 'list2' => [1, 3.14, 6.626e-34], 'list3' => ['foo' => 1, 2.0, 'bar' => 3, 4], 'empty' => [], 'index' => [5 => true, 2 => false], "multiline{$esc}key" => "This string has \"double quotes\"\\x2c 'single quotes'\\x2c and commas.", 'classes' => [Salient\Tests\Core\Utility\Get\GetTest::class, GetTest::class, 'gettest'], "This string has line 1\\x2c{$esc}line 2\\x2c and no more lines.", '\\\\Vendor\\\\Namespace\\\\', "\\xa0", '👩🏼‍🚒']
EOF,
                $array,
                $classes,
                [],
                ', ',
                ' => ',
                ',',
            ],
            'multiline + constants' => [
                <<<EOF
[
    'list1' => [
        1,
    ],
    'list2' => [
        1,
        3.14,
        6.626e-34,
    ],
    'list3' => [
        'foo' => 1,
        2.0,
        'bar' => 3,
        4,
    ],
    'empty' => [],
    'index' => [
        5 => true,
        2 => false,
    ],
    'multiline' . \PHP_EOL . 'key' => 'This string has "double quotes", \'single quotes\', and commas.',
    'classes' => [
        Salient\Tests\Core\Utility\Get\GetTest::class,
        GetTest::class,
        'gettest',
    ],
    'This string has line 1,' . \PHP_EOL . 'line 2, and no more lines.',
    '\\\\Vendor\\\\Namespace\\\\',
    "\\xa0",
    '👩🏼‍🚒',
]
EOF,
                $array,
                $classes,
                $constants,
                ',' . \PHP_EOL,
            ],
            'escaped commas + constants' => [
                <<<EOF
['list1' => [1], 'list2' => [1, 3.14, 6.626e-34], 'list3' => ['foo' => 1, 2.0, 'bar' => 3, 4], 'empty' => [], 'index' => [5 => true, 2 => false], 'multiline' . \PHP_EOL . 'key' => "This string has \"double quotes\"\\x2c 'single quotes'\\x2c and commas.", 'classes' => [Salient\Tests\Core\Utility\Get\GetTest::class, GetTest::class, 'gettest'], "This string has line 1\\x2c" . \PHP_EOL . "line 2\\x2c and no more lines.", '\\\\Vendor\\\\Namespace\\\\', "\\xa0", '👩🏼‍🚒']
EOF,
                $array,
                $classes,
                $constants,
                ', ',
                ' => ',
                ',',
            ],
        ];
    }

    /**
     * @dataProvider eolProvider
     */
    public function testEol(?string $expected, string $string): void
    {
        $this->assertSame($expected, Get::eol($string));
    }

    /**
     * @return array<string,array{string|null,string}>
     */
    public static function eolProvider(): array
    {
        return [
            'empty string' => [null, ''],
            'no newlines' => [null, 'line'],
            'LF newlines' => ["\n", "line1\nline2\n"],
            'CRLF newlines' => ["\r\n", "line1\r\nline2\r\n"],
            'CR newlines' => ["\r", "line1\rline2\r"],
        ];
    }

    public function testCopy(): void
    {
        $a = new ClassWithRefs();
        $b = Get::copy($a, [], 0);

        $this->assertEquals($a, $b);
        $this->assertNotSame($a, $b);

        $a->bind();
        $a->apply(1, 'a', [1.0], $A = new ClassWithValue('A'));
        $b->bind();
        $b->apply(2, 'b', [2.0], $B = new ClassWithValue('B'));

        // $a was copied before binding, so $b should have different values
        $this->assertCopyHas($a, 1, 'a', [1.0], $A, true, true);
        $this->assertCopyHas($b, 2, 'b', [2.0], $B, true, true);

        $c = Get::copy($b, [], 0);

        // $b was copied without ASSIGN_PROPERTIES_BY_REFERENCE, so bound
        // properties should be shared between $b and $c, and they should have
        // received clones, but properties assigned by value should be intact
        $this->assertSame($b->Qux, $c->Qux);
        $this->assertEquals($B, $b->Qux);
        $this->assertNotSame($B, $b->Qux);
        $this->assertSame($B, $b->QuxByVal);

        // The same object should have been copied once
        $this->assertSame($c->Qux, $c->QuxByVal);

        $c->bind();
        $c->apply(3, 'c', [3.0], $C = new ClassWithValue('C'));

        // The above should hold true after binding, i.e. $b's bound properties
        // should be the same as $c's, but other properties should be unchanged
        $this->assertCopyHas($c, 3, 'c', [3.0], $C, true, true);
        $this->assertCopyHas($b, 3, 'c', [3.0], $C);
        $this->assertCopyHas($b, 2, 'b', [2.0], $B, true);

        $d = Get::copy($c);
        $d->bind();
        $d->apply(4, 'd', [4.0], $D = new ClassWithValue('D'));

        // $c was copied with ASSIGN_PROPERTIES_BY_REFERENCE, so bound
        // properties should be properly isolated
        $this->assertCopyHas($c, 3, 'c', [3.0], $C, true, true);
        $this->assertCopyHas($d, 4, 'd', [4.0], $D, true, true);

        $e = new DateTimeImmutable();
        $f = Get::copy($e);
        $this->assertEquals($e, $f);
        $this->assertNotSame($e, $f);

        $g = new ClassWithValue(\STDOUT);
        $this->assertSame(\STDOUT, Get::copy($g)->Value);
    }

    public function testCopyContainersAndSingletons(): void
    {
        $container = new Container();
        $singleton = new SingletonWithContainer($container);
        $a = Get::copy($container);
        $b = Get::copy($container, [], CopyFlag::COPY_CONTAINERS);
        $c = Get::copy($singleton);
        $d = Get::copy($singleton, [], CopyFlag::COPY_SINGLETONS);
        $e = Get::copy($singleton, [], CopyFlag::COPY_CONTAINERS | CopyFlag::COPY_SINGLETONS);

        $this->assertSame($container, $a);
        $this->assertEquals($container, $b);
        $this->assertNotSame($container, $b);
        $this->assertSame($singleton, $c);
        $this->assertEquals($singleton, $d);
        $this->assertNotSame($singleton, $d);
        $this->assertSame($singleton->Container, $d->Container);
        $this->assertEquals($singleton, $e);
        $this->assertNotSame($singleton, $e);
        $this->assertEquals($singleton->Container, $e->Container);
        $this->assertNotSame($singleton->Container, $e->Container);
    }

    public function testCopyObjectWithCloneMethod(): void
    {
        $a = new ClassWithCloneMethod();
        $b = Get::copy($a);
        $c = Get::copy($a, [], CopyFlag::TRUST_CLONE_METHODS);

        $this->assertNotSame($a, $b);
        $this->assertNotSame($a, $c);
        $this->assertNotSame($a->Foo, $b->Foo);
        $this->assertSame($a->Foo, $c->Foo);
    }

    public function testCopyUncloneable(): void
    {
        $a = new UncloneableClass();
        $b = Get::copy($a);
        $this->assertSame($a, $b);

        $this->expectException(UncloneableObjectException::class);
        $this->expectExceptionMessage(sprintf('%s cannot be copied', UncloneableClass::class));
        Get::copy($a, [], 0);
    }

    public function testCopyWithSkip(): void
    {
        $object = new ClassWithValue(__METHOD__);
        $property = 'Object';
        $a = new stdClass();
        $a->$property = $object;

        $b = Get::copy($a);
        $c = Get::copy($a, [get_class($object)]);

        $this->assertEquals($a->$property, $b->$property);
        $this->assertNotSame($a->$property, $b->$property);
        $this->assertNotSame($a, $c);
        $this->assertSame($a->$property, $c->$property);
    }

    /**
     * @param mixed[] $baz
     */
    private function assertCopyHas(
        ClassWithRefs $copy,
        int $foo,
        string $bar,
        array $baz,
        ?object $qux = null,
        bool $byVal = false,
        bool $byRef = true
    ): void {
        $this->assertSame($foo, $byVal ? $copy->FooByVal : $copy->Foo);
        $this->assertSame($bar, $byVal ? $copy->BarByVal : $copy->Bar);
        $this->assertSame($baz, $byVal ? $copy->BazByVal : $copy->Baz);
        if ($qux !== null) {
            $this->assertSame($qux, $byVal ? $copy->QuxByVal : $copy->Qux);
        }
        if ($byVal && $byRef && func_num_args() >= 7) {
            $this->assertCopyHas($copy, $foo, $bar, $baz, $qux);
        }
    }
}

class ClassWithRefs
{
    public int $Foo = 0;
    public string $Bar = '';
    /** @var mixed[] */
    public array $Baz = [];
    public ?object $Qux = null;
    public int $FooByVal;
    public string $BarByVal;
    /** @var mixed[] */
    public array $BazByVal;
    public object $QuxByVal;
    /** @var RefClass[] */
    public array $Refs;

    public function bind(): void
    {
        $this->Refs = [];
        $this->Refs[] = new RefClass($this->Foo);
        $this->Refs[] = new RefClass($this->Bar);
        $this->Refs[] = new RefClass($this->Baz);
        $this->Refs[] = new RefClass($this->Qux);
    }

    public function unbind(): void
    {
        $this->Refs = [];
    }

    /**
     * @param mixed[] $baz
     */
    public function apply(int $foo, string $bar, array $baz, object $qux): void
    {
        $this->Refs[0]->apply($foo);
        $this->Refs[1]->apply($bar);
        $this->Refs[2]->apply($baz);
        $this->Refs[3]->apply($qux);
        $this->FooByVal = $foo;
        $this->BarByVal = $bar;
        $this->BazByVal = $baz;
        $this->QuxByVal = $qux;
    }
}

class ClassWithValue
{
    /** @var mixed */
    public $Value;

    /**
     * @param mixed $value
     */
    public function __construct($value)
    {
        $this->Value = $value;
    }
}

class SingletonWithContainer implements HasServices, HasContextualBindings, SingletonInterface
{
    public ContainerInterface $Container;

    public function __construct(ContainerInterface $container)
    {
        $this->Container = $container;
    }

    public static function getServices(): array
    {
        return [];
    }

    public static function getContextualBindings(): array
    {
        return [];
    }
}

class ClassWithCloneMethod
{
    public static int $Instances = 0;
    public object $Foo;

    public function __construct()
    {
        self::$Instances++;
        $this->Foo = new stdClass();
    }

    public function __clone()
    {
        self::$Instances++;
    }
}

class UncloneableClass
{
    private function __clone() {}
}

class RefClass
{
    /** @var mixed */
    public $BindTo;

    /**
     * @param mixed $bindTo
     */
    public function __construct(&$bindTo)
    {
        $this->BindTo = &$bindTo;
    }

    /**
     * @param mixed $value
     */
    public function apply($value): void
    {
        $this->BindTo = $value;
    }
}
