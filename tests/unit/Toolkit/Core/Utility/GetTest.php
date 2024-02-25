<?php declare(strict_types=1);

namespace Salient\Tests\Core\Utility;

use Salient\Container\Container;
use Salient\Core\Catalog\CopyFlag;
use Salient\Core\Catalog\QueryFlag;
use Salient\Core\Contract\Arrayable;
use Salient\Core\Exception\InvalidArgumentException;
use Salient\Core\Exception\UncloneableObjectException;
use Salient\Core\Utility\Get;
use Salient\Core\DateFormatter;
use Salient\Tests\Core\Utility\Get\ClassWithCloneMethod;
use Salient\Tests\Core\Utility\Get\ClassWithRefs;
use Salient\Tests\Core\Utility\Get\SingletonWithContainer;
use Salient\Tests\Core\Utility\Get\UncloneableClass;
use Salient\Tests\TestCase;
use ArrayIterator;
use ArrayObject;
use Countable;
use DateTimeImmutable;
use DateTimeInterface;
use stdClass;
use Traversable;

/**
 * @covers \Salient\Core\Utility\Get
 */
final class GetTest extends TestCase
{
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
            '0' => [false, 0],
            '1' => [true, 1],
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
     * @param mixed $value
     */
    public function testInteger(?int $expected, $value): void
    {
        $this->assertSame($expected, Get::integer($value));
    }

    /**
     * @return array<string,array{int|null,mixed}>
     */
    public static function integerProvider(): array
    {
        return [
            'null' => [null, null],
            'false' => [0, false],
            'true' => [1, true],
            '5' => [5, 5],
            '5.5' => [5, 5.5],
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

    public function testFilter(): void
    {
        $this->assertSame([
            'key1' => 'value1',
            'key2' => 'value2',
            'key3' => '',
            'key4' => '',
        ], Get::filter(['key1=value1', 'key2=value2', 'key3=value3', 'key3=', 'key4', '=value5']));
    }

    /**
     * @dataProvider queryProvider
     *
     * @param mixed[] $data
     * @param int-mask-of<QueryFlag::*> $flags
     */
    public function testQuery(
        string $expected,
        array $data,
        int $flags = QueryFlag::PRESERVE_NUMERIC_KEYS | QueryFlag::PRESERVE_STRING_KEYS,
        ?DateFormatter $dateFormatter = null
    ): void {
        $this->assertSame($expected, Get::query($data, $flags, $dateFormatter));
    }

    /**
     * @return array<array{string,mixed[],2?:int-mask-of<QueryFlag::*>,3?:DateFormatter}>
     */
    public static function queryProvider(): array
    {
        $data = [
            'user_id' => 7654,
            'fields' => [
                'surname' => 'Williams',
                'email' => 'JWilliams432@gmail.com',
                'notify_by' => [
                    'email',
                    'sms',
                ],
                'created' => new DateTimeImmutable('2021-10-02T17:23:14+10:00'),
            ],
        ];

        return [
            [
                // user_id=7654&fields[surname]=Williams&fields[email]=JWilliams432@gmail.com&fields[notify_by][]=email&fields[notify_by][]=sms&fields[created]=2021-10-02T17:23:14+10:00
                'user_id=7654&fields%5Bsurname%5D=Williams&fields%5Bemail%5D=JWilliams432%40gmail.com&fields%5Bnotify_by%5D%5B%5D=email&fields%5Bnotify_by%5D%5B%5D=sms&fields%5Bcreated%5D=2021-10-02T17%3A23%3A14%2B10%3A00',
                $data,
            ],
            [
                // user_id=7654&fields[surname]=Williams&fields[email]=JWilliams432@gmail.com&fields[notify_by][0]=email&fields[notify_by][1]=sms&fields[created]=2021-10-02T17:23:14+10:00
                'user_id=7654&fields%5Bsurname%5D=Williams&fields%5Bemail%5D=JWilliams432%40gmail.com&fields%5Bnotify_by%5D%5B0%5D=email&fields%5Bnotify_by%5D%5B1%5D=sms&fields%5Bcreated%5D=2021-10-02T17%3A23%3A14%2B10%3A00',
                $data,
                QueryFlag::PRESERVE_ALL_KEYS,
            ],
            [
                // user_id=7654&fields[surname]=Williams&fields[email]=JWilliams432@gmail.com&fields[notify_by][]=email&fields[notify_by][]=sms&fields[created]=Sat, 02 Oct 2021 17:23:14 +1000
                'user_id=7654&fields%5Bsurname%5D=Williams&fields%5Bemail%5D=JWilliams432%40gmail.com&fields%5Bnotify_by%5D%5B%5D=email&fields%5Bnotify_by%5D%5B%5D=sms&fields%5Bcreated%5D=Sat%2C%2002%20Oct%202021%2017%3A23%3A14%20%2B1000',
                $data,
                QueryFlag::PRESERVE_NUMERIC_KEYS | QueryFlag::PRESERVE_STRING_KEYS,
                new DateFormatter(DateTimeInterface::RSS),
            ],
            [
                // user_id=7654&fields[surname]=Williams&fields[email]=JWilliams432@gmail.com&fields[notify_by][]=email&fields[notify_by][]=sms&fields[created]=2021-10-02T07:23:14+00:00
                'user_id=7654&fields%5Bsurname%5D=Williams&fields%5Bemail%5D=JWilliams432%40gmail.com&fields%5Bnotify_by%5D%5B%5D=email&fields%5Bnotify_by%5D%5B%5D=sms&fields%5Bcreated%5D=2021-10-02T07%3A23%3A14%2B00%3A00',
                $data,
                QueryFlag::PRESERVE_NUMERIC_KEYS | QueryFlag::PRESERVE_STRING_KEYS,
                new DateFormatter(DateTimeInterface::ATOM, 'UTC'),
            ],
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
        $f = fopen(__FILE__, 'r');
        fclose($f);

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
        $f = fopen(__FILE__, 'r');
        $this->assertSame('resource (stream)', Get::type($f));
        fclose($f);
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
     */
    public function testCode(
        string $expected,
        $value,
        array $classes = [],
        string $delimiter = ', ',
        string $arrow = ' => ',
        ?string $escapeCharacters = null,
        string $tab = '    '
    ): void {
        $this->assertSame($expected, Get::code($value, $delimiter, $arrow, $escapeCharacters, $tab, $classes));
    }

    /**
     * @return array<string,array{string,mixed,2?:string[],3?:string,4?:string,5?:string|null,6?:string}>
     */
    public static function codeProvider(): array
    {
        $array = [
            'list1' => [1, 2.0, 3.14, 6.626e-34],
            'list2' => [1],
            'empty' => [],
            'index' => [5 => 'a', 9 => 'b', 2 => 'c'],
            "multiline\nkey" => 'This string has "double quotes", \'single quotes\', and commas.',
            'bool1' => true,
            'bool2' => false,
            'classes' => [static::class, Get::basename(static::class), 'gettest'],
        ];
        $classes = [static::class, Get::basename(static::class)];

        return [
            'default' => [
                <<<'EOF'
                ['list1' => [1, 2.0, 3.14, 6.626e-34], 'list2' => [1], 'empty' => [], 'index' => [5 => 'a', 9 => 'b', 2 => 'c'], "multiline\nkey" => 'This string has "double quotes", \'single quotes\', and commas.', 'bool1' => true, 'bool2' => false, 'classes' => [Salient\Tests\Core\Utility\GetTest::class, GetTest::class, 'gettest']]
                EOF,
                $array,
                $classes,
            ],
            'compact' => [
                <<<'EOF'
                ['list1'=>[1,2.0,3.14,6.626e-34],'list2'=>[1],'empty'=>[],'index'=>[5=>'a',9=>'b',2=>'c'],"multiline\nkey"=>'This string has "double quotes", \'single quotes\', and commas.','bool1'=>true,'bool2'=>false,'classes'=>[Salient\Tests\Core\Utility\GetTest::class,GetTest::class,'gettest']]
                EOF,
                $array,
                $classes,
                ',',
                '=>',
            ],
            'multiline' => [
                <<<'EOF'
                [
                    'list1' => [
                        1,
                        2.0,
                        3.14,
                        6.626e-34,
                    ],
                    'list2' => [
                        1,
                    ],
                    'empty' => [],
                    'index' => [
                        5 => 'a',
                        9 => 'b',
                        2 => 'c',
                    ],
                    "multiline\nkey" => 'This string has "double quotes", \'single quotes\', and commas.',
                    'bool1' => true,
                    'bool2' => false,
                    'classes' => [
                        Salient\Tests\Core\Utility\GetTest::class,
                        GetTest::class,
                        'gettest',
                    ],
                ]
                EOF,
                $array,
                $classes,
                ',' . \PHP_EOL,
            ],
            'escaped commas' => [
                <<<'EOF'
                ['list1' => [1, 2.0, 3.14, 6.626e-34], 'list2' => [1], 'empty' => [], 'index' => [5 => 'a', 9 => 'b', 2 => 'c'], "multiline\nkey" => "This string has "double quotes"\x2c 'single quotes'\x2c and commas.", 'bool1' => true, 'bool2' => false, 'classes' => [Salient\Tests\Core\Utility\GetTest::class, GetTest::class, 'gettest']]
                EOF,
                $array,
                $classes,
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
        $a->apply(1, 'a', [1.0], $A = $this->getObject('A'));
        $b->bind();
        $b->apply(2, 'b', [2.0], $B = $this->getObject('B'));

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
        $c->apply(3, 'c', [3.0], $C = $this->getObject('C'));

        // The above should hold true after binding, i.e. $b's bound properties
        // should be the same as $c's, but other properties should be unchanged
        $this->assertCopyHas($c, 3, 'c', [3.0], $C, true, true);
        $this->assertCopyHas($b, 3, 'c', [3.0], $C);
        $this->assertCopyHas($b, 2, 'b', [2.0], $B, true);

        $d = Get::copy($c);
        $d->bind();
        $d->apply(4, 'd', [4.0], $D = $this->getObject('D'));

        // $c was copied with ASSIGN_PROPERTIES_BY_REFERENCE, so bound
        // properties should be properly isolated
        $this->assertCopyHas($c, 3, 'c', [3.0], $C, true, true);
        $this->assertCopyHas($d, 4, 'd', [4.0], $D, true, true);

        $e = new DateTimeImmutable();
        $f = Get::copy($e);
        $this->assertEquals($e, $f);
        $this->assertNotSame($e, $f);

        $g = $this->getObject(\STDOUT);
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
        $object = $this->getObject(__METHOD__);
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

    /**
     * @param mixed $value
     */
    private function getObject($value): object
    {
        return new class($value) {
            /**
             * @var mixed
             */
            public $Value;

            /**
             * @param mixed $value
             */
            public function __construct($value)
            {
                $this->Value = $value;
            }
        };
    }
}
