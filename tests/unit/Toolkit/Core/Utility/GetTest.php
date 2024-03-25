<?php declare(strict_types=1);

namespace Salient\Tests\Core\Utility;

use Salient\Container\Container;
use Salient\Contract\Core\Arrayable;
use Salient\Contract\Core\CopyFlag;
use Salient\Contract\Core\QueryFlag;
use Salient\Core\Exception\InvalidArgumentException;
use Salient\Core\Exception\UncloneableObjectException;
use Salient\Core\Utility\File;
use Salient\Core\Utility\Get;
use Salient\Core\DateFormatter;
use Salient\Tests\Core\Utility\Get\ClassWithCloneMethod;
use Salient\Tests\Core\Utility\Get\ClassWithRefs;
use Salient\Tests\Core\Utility\Get\ClassWithValue;
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

    /**
     * @dataProvider filterProvider
     *
     * @param array<string,mixed>|string $expected
     * @param string[] $values
     */
    public function testFilter($expected, array $values, bool $discardInvalid = true): void
    {
        $this->maybeExpectException($expected);
        $this->assertSame($expected, Get::filter($values, $discardInvalid));
    }

    /**
     * @return array<array{array<string,mixed>|string,string[],2?:bool}>
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
     * @dataProvider queryProvider
     *
     * @param mixed[] $data
     * @param int-mask-of<QueryFlag::*> $flags
     */
    public function testQuery(
        string $expected,
        array $data,
        int $flags = QueryFlag::PRESERVE_NUMERIC_KEYS | QueryFlag::PRESERVE_STRING_KEYS,
        ?DateFormatter $dateFormatter = null,
        bool $parse = true
    ): void {
        $query = Get::query($data, $flags, $dateFormatter);
        $this->assertSame($expected, $query);

        if (!$parse) {
            return;
        }

        array_walk_recursive(
            $data,
            function (&$value) use (&$dateFormatter): void {
                if ($value instanceof DateTimeInterface) {
                    $dateFormatter ??= new DateFormatter();
                    $value = $dateFormatter->format($value);
                } elseif (!is_string($value)) {
                    $value = (string) $value;
                }
            },
        );
        parse_str($query, $parsed);
        $this->assertSame($data, $parsed);
    }

    /**
     * @return array<array{string,mixed[],2?:int-mask-of<QueryFlag::*>,3?:DateFormatter|null,4?:bool}>
     */
    public static function queryProvider(): array
    {
        $data = [
            'user_id' => 7654,
            'fields' => [
                'surname' => 'Williams',
                'email' => 'JWilliams432@gmail.com',
                'notify_by' => [
                    ['email', 'sms'],
                    ['mobile', 'home'],
                ],
                'groups' => ['staff', 'editor'],
                'created' => new DateTimeImmutable('2021-10-02T17:23:14+10:00'),
            ],
        ];

        $lists = [
            'list' => ['a', 'b', 'c'],
            'indexed' => [5 => 'a', 9 => 'b', 2 => 'c'],
            'associative' => ['a' => 5, 'b' => 9, 'c' => 2],
        ];

        return [
            [
                // user_id=7654&fields[surname]=Williams&fields[email]=JWilliams432@gmail.com&fields[notify_by][0][]=email&fields[notify_by][0][]=sms&fields[notify_by][1][]=mobile&fields[notify_by][1][]=home&fields[groups][]=staff&fields[groups][]=editor&fields[created]=2021-10-02T17:23:14+10:00
                'user_id=7654&fields%5Bsurname%5D=Williams&fields%5Bemail%5D=JWilliams432%40gmail.com&fields%5Bnotify_by%5D%5B0%5D%5B%5D=email&fields%5Bnotify_by%5D%5B0%5D%5B%5D=sms&fields%5Bnotify_by%5D%5B1%5D%5B%5D=mobile&fields%5Bnotify_by%5D%5B1%5D%5B%5D=home&fields%5Bgroups%5D%5B%5D=staff&fields%5Bgroups%5D%5B%5D=editor&fields%5Bcreated%5D=2021-10-02T17%3A23%3A14%2B10%3A00',
                $data,
            ],
            [
                // user_id=7654&fields[surname]=Williams&fields[email]=JWilliams432@gmail.com&fields[notify_by][0][0]=email&fields[notify_by][0][1]=sms&fields[notify_by][1][0]=mobile&fields[notify_by][1][1]=home&fields[groups][0]=staff&fields[groups][1]=editor&fields[created]=2021-10-02T17:23:14+10:00
                'user_id=7654&fields%5Bsurname%5D=Williams&fields%5Bemail%5D=JWilliams432%40gmail.com&fields%5Bnotify_by%5D%5B0%5D%5B0%5D=email&fields%5Bnotify_by%5D%5B0%5D%5B1%5D=sms&fields%5Bnotify_by%5D%5B1%5D%5B0%5D=mobile&fields%5Bnotify_by%5D%5B1%5D%5B1%5D=home&fields%5Bgroups%5D%5B0%5D=staff&fields%5Bgroups%5D%5B1%5D=editor&fields%5Bcreated%5D=2021-10-02T17%3A23%3A14%2B10%3A00',
                $data,
                QueryFlag::PRESERVE_ALL_KEYS,
            ],
            [
                // user_id=7654&fields[surname]=Williams&fields[email]=JWilliams432@gmail.com&fields[notify_by][0][]=email&fields[notify_by][0][]=sms&fields[notify_by][1][]=mobile&fields[notify_by][1][]=home&fields[groups][]=staff&fields[groups][]=editor&fields[created]=Sat, 02 Oct 2021 17:23:14 +1000
                'user_id=7654&fields%5Bsurname%5D=Williams&fields%5Bemail%5D=JWilliams432%40gmail.com&fields%5Bnotify_by%5D%5B0%5D%5B%5D=email&fields%5Bnotify_by%5D%5B0%5D%5B%5D=sms&fields%5Bnotify_by%5D%5B1%5D%5B%5D=mobile&fields%5Bnotify_by%5D%5B1%5D%5B%5D=home&fields%5Bgroups%5D%5B%5D=staff&fields%5Bgroups%5D%5B%5D=editor&fields%5Bcreated%5D=Sat%2C%2002%20Oct%202021%2017%3A23%3A14%20%2B1000',
                $data,
                QueryFlag::PRESERVE_NUMERIC_KEYS | QueryFlag::PRESERVE_STRING_KEYS,
                new DateFormatter(DateTimeInterface::RSS),
            ],
            [
                // user_id=7654&fields[surname]=Williams&fields[email]=JWilliams432@gmail.com&fields[notify_by][0][]=email&fields[notify_by][0][]=sms&fields[notify_by][1][]=mobile&fields[notify_by][1][]=home&fields[groups][]=staff&fields[groups][]=editor&fields[created]=2021-10-02T07:23:14+00:00
                'user_id=7654&fields%5Bsurname%5D=Williams&fields%5Bemail%5D=JWilliams432%40gmail.com&fields%5Bnotify_by%5D%5B0%5D%5B%5D=email&fields%5Bnotify_by%5D%5B0%5D%5B%5D=sms&fields%5Bnotify_by%5D%5B1%5D%5B%5D=mobile&fields%5Bnotify_by%5D%5B1%5D%5B%5D=home&fields%5Bgroups%5D%5B%5D=staff&fields%5Bgroups%5D%5B%5D=editor&fields%5Bcreated%5D=2021-10-02T07%3A23%3A14%2B00%3A00',
                $data,
                QueryFlag::PRESERVE_NUMERIC_KEYS | QueryFlag::PRESERVE_STRING_KEYS,
                new DateFormatter(DateTimeInterface::ATOM, 'UTC'),
            ],
            [
                // list[]=a&list[]=b&list[]=c&indexed[5]=a&indexed[9]=b&indexed[2]=c&associative[a]=5&associative[b]=9&associative[c]=2
                'list%5B%5D=a&list%5B%5D=b&list%5B%5D=c&indexed%5B5%5D=a&indexed%5B9%5D=b&indexed%5B2%5D=c&associative%5Ba%5D=5&associative%5Bb%5D=9&associative%5Bc%5D=2',
                $lists,
            ],
            [
                // list[]=a&list[]=b&list[]=c&indexed[]=a&indexed[]=b&indexed[]=c&associative[]=5&associative[]=9&associative[]=2
                'list%5B%5D=a&list%5B%5D=b&list%5B%5D=c&indexed%5B%5D=a&indexed%5B%5D=b&indexed%5B%5D=c&associative%5B%5D=5&associative%5B%5D=9&associative%5B%5D=2',
                $lists,
                0,
                null,
                false,
            ],
            [
                // list[]=a&list[]=b&list[]=c&indexed[]=a&indexed[]=b&indexed[]=c&associative[a]=5&associative[b]=9&associative[c]=2
                'list%5B%5D=a&list%5B%5D=b&list%5B%5D=c&indexed%5B%5D=a&indexed%5B%5D=b&indexed%5B%5D=c&associative%5Ba%5D=5&associative%5Bb%5D=9&associative%5Bc%5D=2',
                $lists,
                QueryFlag::PRESERVE_STRING_KEYS,
                null,
                false,
            ],
            [
                // list[0]=a&list[1]=b&list[2]=c&indexed[5]=a&indexed[9]=b&indexed[2]=c&associative[]=5&associative[]=9&associative[]=2
                'list%5B0%5D=a&list%5B1%5D=b&list%5B2%5D=c&indexed%5B5%5D=a&indexed%5B9%5D=b&indexed%5B2%5D=c&associative%5B%5D=5&associative%5B%5D=9&associative%5B%5D=2',
                $lists,
                QueryFlag::PRESERVE_LIST_KEYS | QueryFlag::PRESERVE_NUMERIC_KEYS,
                null,
                false,
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
