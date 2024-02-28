<?php declare(strict_types=1);

namespace Salient\Tests\Core;

use Salient\Contract\Core\ConvertibleEnumerationInterface;
use Salient\Contract\Core\EnumerationInterface;
use Salient\Tests\Core\AbstractCatalog\MyArrayEnum;
use Salient\Tests\Core\AbstractCatalog\MyConvertibleEnum;
use Salient\Tests\Core\AbstractCatalog\MyDictionary;
use Salient\Tests\Core\AbstractCatalog\MyEmptyReflectiveEnum;
use Salient\Tests\Core\AbstractCatalog\MyIntEnum;
use Salient\Tests\Core\AbstractCatalog\MyInvalidReflectiveEnum;
use Salient\Tests\Core\AbstractCatalog\MyReflectiveEnum;
use Salient\Tests\Core\AbstractCatalog\MyReflectiveFloatEnum;
use Salient\Tests\Core\AbstractCatalog\MyRepeatedValueEnum;
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
                'Argument #1 ($name) is invalid: QUX',
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
                'Argument #1 ($value) is invalid: 3',
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
        $this->expectExceptionMessage('Public constants are not unique: ' . MyInvalidReflectiveEnum::class);
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
