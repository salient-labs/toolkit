<?php declare(strict_types=1);

namespace Salient\Tests\Core\AbstractCatalog;

use Salient\Contract\Core\ConvertibleEnumerationInterface;
use Salient\Contract\Core\EnumerationInterface;
use Salient\Core\AbstractConvertibleEnumeration;
use Salient\Core\AbstractDictionary;
use Salient\Core\AbstractEnumeration;
use Salient\Core\AbstractReflectiveEnumeration;
use Salient\Tests\TestCase;
use LogicException;
use Throwable;

/**
 * @covers \Salient\Core\AbstractCatalog
 * @covers \Salient\Core\AbstractEnumeration
 * @covers \Salient\Core\AbstractConvertibleEnumeration
 * @covers \Salient\Core\AbstractReflectiveEnumeration
 * @covers \Salient\Core\AbstractDictionary
 */
final class AbstractCatalogTest extends TestCase
{
    public function testIntegerEnumeration(): void
    {
        $this->assertSame([
            'FOO' => 0,
            'BAR' => 1,
            'BAZ' => 2,
        ], MyIntEnum::cases());
        $this->assertTrue(MyIntEnum::hasValue(0));
        $this->assertTrue(MyIntEnum::hasValue(1));
        $this->assertFalse(MyIntEnum::hasValue(3));
        // @phpstan-ignore-next-line
        $this->assertFalse(MyIntEnum::hasValue(null));
        // @phpstan-ignore-next-line
        $this->assertFalse(MyIntEnum::hasValue(false));
        // @phpstan-ignore-next-line
        $this->assertFalse(MyIntEnum::hasValue(true));
        // @phpstan-ignore-next-line
        $this->assertFalse(MyIntEnum::hasValue(''));
        // @phpstan-ignore-next-line
        $this->assertFalse(MyIntEnum::hasValue('foo'));
        // @phpstan-ignore-next-line
        $this->assertFalse(MyIntEnum::hasValue([0]));
        // @phpstan-ignore-next-line
        $this->assertFalse(MyIntEnum::hasValue([1]));
        // @phpstan-ignore-next-line
        $this->assertFalse(MyIntEnum::hasValue([]));

        $this->assertSame([
            'FOO' => 0,
            'BAR' => 1,
            'BAZ' => 2,
            'QUX' => 2,
        ], MyRepeatedValueEnum::cases());
        $this->assertTrue(MyRepeatedValueEnum::hasValue(2));
    }

    public function testArrayEnumeration(): void
    {
        $this->assertSame([
            'FOO' => [0, 1, 2],
            'BAR' => [1, 2],
            'BAZ' => [2],
        ], MyArrayEnum::cases());
        $this->assertTrue(MyArrayEnum::hasValue([0, 1, 2]));
        $this->assertTrue(MyArrayEnum::hasValue([1, 2]));
        $this->assertTrue(MyArrayEnum::hasValue([2]));
        $this->assertFalse(MyArrayEnum::hasValue([0, 1]));
        $this->assertFalse(MyArrayEnum::hasValue([0]));
        $this->assertFalse(MyArrayEnum::hasValue([1]));
        $this->assertFalse(MyArrayEnum::hasValue([]));
        // @phpstan-ignore-next-line
        $this->assertFalse(MyArrayEnum::hasValue(3));
        // @phpstan-ignore-next-line
        $this->assertFalse(MyArrayEnum::hasValue(null));
        // @phpstan-ignore-next-line
        $this->assertFalse(MyArrayEnum::hasValue(false));
        // @phpstan-ignore-next-line
        $this->assertFalse(MyArrayEnum::hasValue(true));
        // @phpstan-ignore-next-line
        $this->assertFalse(MyArrayEnum::hasValue(''));
        // @phpstan-ignore-next-line
        $this->assertFalse(MyArrayEnum::hasValue('foo'));
    }

    /**
     * @dataProvider convertibleEnumerationProvider
     *
     * @param class-string<ConvertibleEnumerationInterface<int>> $enum
     */
    public function testConvertibleEnumeration(string $enum): void
    {
        $this->assertSame([
            'FOO' => 0,
            'BAR' => 1,
            'BAZ' => 2,
        ], $enum::cases());
        $this->assertTrue($enum::hasValue(0));
        $this->assertFalse($enum::hasValue(3));
        $this->assertSame('FOO', $enum::toName(0));
        $this->assertSame('BAR', $enum::toName(1));
        $this->assertSame(['BAR', 'FOO'], $enum::toNames([1, 0]));
        $this->assertSame(0, $enum::fromName('FOO'));
        $this->assertSame(1, $enum::fromName('BAR'));
        $this->assertSame(2, $enum::fromName('baz'));
        $this->assertSame([1, 0], $enum::fromNames(['BAR', 'FOO']));
        $this->assertSame([1, 0, 2], $enum::fromNames(['BAR', 'FOO', 'baz']));
    }

    /**
     * @return array<array{class-string<ConvertibleEnumerationInterface<int>>}>
     */
    public static function convertibleEnumerationProvider(): array
    {
        return [
            [MyConvertibleEnum::class],
            [MyReflectiveEnum::class],
        ];
    }

    /**
     * @dataProvider invalidInputProvider
     *
     * @param class-string<Throwable> $exception
     * @param class-string<EnumerationInterface<int>> $enum
     * @param mixed[] $args
     */
    public function testInvalidInput(
        string $exception,
        string $message,
        string $enum,
        string $method,
        array $args = []
    ): void {
        $this->expectException($exception);
        $this->expectExceptionMessage($message);
        $enum::$method(...$args);
    }

    /**
     * @return array<array{class-string<Throwable>,string,class-string<EnumerationInterface<int>>,string,4?:mixed[]}>
     */
    public function invalidInputProvider(): array
    {
        $data = [
            [
                LogicException::class,
                'Invalid name: QUX',
                MyConvertibleEnum::class,
                'fromName',
                ['QUX'],
            ],
            [
                LogicException::class,
                'Invalid name: QUX',
                MyConvertibleEnum::class,
                'fromNames',
                [['QUX']],
            ],
            [
                LogicException::class,
                'Invalid names: QUX,QUUX',
                MyConvertibleEnum::class,
                'fromNames',
                [['QUX', 'QUUX']],
            ],
            [
                LogicException::class,
                'Invalid value: 3',
                MyConvertibleEnum::class,
                'toName',
                [3],
            ],
            [
                LogicException::class,
                'Invalid value: 3',
                MyConvertibleEnum::class,
                'toNames',
                [[0, 1, 3]],
            ],
            [
                LogicException::class,
                'Invalid values: 3,10',
                MyConvertibleEnum::class,
                'toNames',
                [[0, 1, 3, 10]],
            ],
        ];

        foreach ($data as $args) {
            $args[2] = MyReflectiveEnum::class;
            $data[] = $args;
        }

        return $data;
    }

    public function testEmptyEnumeration(): void
    {
        $this->assertSame([], MyEmptyReflectiveEnum::cases());
        $this->assertFalse(MyEmptyReflectiveEnum::hasValue(0));
    }

    public function testInvalidEnumerationType(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Public constant is not of type int|string: ' . MyReflectiveFloatEnum::class . '::FOO');
        MyReflectiveFloatEnum::toName(0.0);
    }

    public function testInvalidEnumerationValues(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Public constants do not have unique values: ' . MyInvalidReflectiveEnum::class);
        MyInvalidReflectiveEnum::toName(0);
    }

    public function testDictionary(): void
    {
        $this->assertSame([
            'FOO' => 'Foo',
            'BAR' => 'Bar',
            'BAZ' => 'Baz',
        ], MyDictionary::definitions());
    }
}

/**
 * @extends AbstractEnumeration<int>
 */
class MyIntEnum extends AbstractEnumeration
{
    public const FOO = 0;
    public const BAR = 1;
    public const BAZ = 2;
}

/**
 * @extends AbstractEnumeration<int>
 */
class MyRepeatedValueEnum extends AbstractEnumeration
{
    public const FOO = 0;
    public const BAR = 1;
    public const BAZ = 2;
    public const QUX = 2;
}

/**
 * @extends AbstractEnumeration<int[]>
 */
class MyArrayEnum extends AbstractEnumeration
{
    public const FOO = [0, 1, 2];
    public const BAR = [1, 2];
    public const BAZ = [2];
}

/**
 * @extends AbstractConvertibleEnumeration<int>
 */
class MyConvertibleEnum extends AbstractConvertibleEnumeration
{
    public const FOO = 0;
    public const BAR = 1;
    public const BAZ = 2;

    protected static $NameMap = [
        self::FOO => 'FOO',
        self::BAR => 'BAR',
        self::BAZ => 'BAZ',
    ];

    protected static $ValueMap = [
        'FOO' => self::FOO,
        'BAR' => self::BAR,
        'BAZ' => self::BAZ,
    ];
}

/**
 * @extends AbstractReflectiveEnumeration<int>
 */
class MyReflectiveEnum extends AbstractReflectiveEnumeration
{
    public const FOO = 0;
    public const BAR = 1;
    public const BAZ = 2;
}

/**
 * @extends AbstractReflectiveEnumeration<int>
 */
class MyEmptyReflectiveEnum extends AbstractReflectiveEnumeration
{
    protected const IS_PUBLIC = false;
}

/**
 * @extends AbstractReflectiveEnumeration<float>
 *
 * @phpstan-ignore generics.notSubtype
 */
class MyReflectiveFloatEnum extends AbstractReflectiveEnumeration
{
    public const FOO = 0.0;
    public const BAR = 1.0;
    public const BAZ = 3.14;
}

/**
 * @extends AbstractReflectiveEnumeration<int>
 */
class MyInvalidReflectiveEnum extends AbstractReflectiveEnumeration
{
    public const FOO = 0;
    public const BAR = 1;
    public const BAZ = 2;
    public const QUX = 2;
}

/**
 * @extends AbstractDictionary<string>
 */
class MyDictionary extends AbstractDictionary
{
    public const FOO = 'Foo';
    public const BAR = 'Bar';
    public const BAZ = 'Baz';
}
