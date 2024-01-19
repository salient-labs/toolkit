<?php declare(strict_types=1);

namespace Lkrms\Tests\Utility;

use Lkrms\Container\Container;
use Lkrms\Exception\UncloneableObjectException;
use Lkrms\Tests\Utility\Get\ClassWithCloneMethod;
use Lkrms\Tests\Utility\Get\ClassWithRefs;
use Lkrms\Tests\Utility\Get\SingletonWithContainer;
use Lkrms\Tests\Utility\Get\UncloneableClass;
use Lkrms\Tests\TestCase;
use Lkrms\Utility\Catalog\CopyFlag;
use Lkrms\Utility\Get;
use DateTimeImmutable;
use stdClass;

final class GetTest extends TestCase
{
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
