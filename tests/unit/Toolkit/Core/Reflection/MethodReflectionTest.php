<?php declare(strict_types=1);

namespace Salient\Tests\Core\Reflection;

use Salient\Core\Reflection\MethodReflection;
use Salient\Tests\TestCase;
use DateTime;
use DateTimeImmutable;
use DateTimeInterface;

/**
 * @covers \Salient\Core\Reflection\MethodReflection
 */
final class MethodReflectionTest extends TestCase
{
    /**
     * @dataProvider acceptsProvider
     *
     * @param object|class-string $objectOrClass
     * @param int<0,max> $position
     */
    public function testAccepts(
        bool $expected,
        $objectOrClass,
        string $method,
        string $typeName,
        bool $isBuiltin = false,
        int $position = 0
    ): void {
        $method = new MethodReflection($objectOrClass, $method);
        $this->assertSame($expected, $method->accepts($typeName, $isBuiltin, $position));
    }

    /**
     * @return array<array{bool,object|class-string,string,string,4?:bool,5?:int<0,max>}>
     */
    public static function acceptsProvider(): array
    {
        $acceptsInterface = new class { function foo(DateTimeInterface $bar): void {} };
        $acceptsImmutable = new class { function foo(DateTimeImmutable $bar): void {} };
        $acceptsDateTime = new class { function foo(DateTime $bar): void {} };

        return [
            [true, $acceptsInterface, 'foo', DateTimeInterface::class],
            [false, $acceptsImmutable, 'foo', DateTimeInterface::class],
            [false, $acceptsDateTime, 'foo', DateTimeInterface::class],
            [true, $acceptsInterface, 'foo', DateTimeImmutable::class],
            [true, $acceptsImmutable, 'foo', DateTimeImmutable::class],
            [false, $acceptsDateTime, 'foo', DateTimeImmutable::class],
            [true, $acceptsInterface, 'foo', DateTime::class],
            [false, $acceptsImmutable, 'foo', DateTime::class],
            [true, $acceptsDateTime, 'foo', DateTime::class],
        ];
    }

    /**
     * @dataProvider returnsProvider
     *
     * @param object|class-string $objectOrClass
     */
    public function testReturns(
        bool $expected,
        $objectOrClass,
        string $method,
        string $typeName,
        bool $isBuiltin = false,
        bool $allowNull = true
    ): void {
        $method = new MethodReflection($objectOrClass, $method);
        $this->assertSame($expected, $method->returns($typeName, $isBuiltin, $allowNull));
    }

    /**
     * @return array<array{bool,object|class-string,string,string,4?:bool,5?:bool}>
     */
    public static function returnsProvider(): array
    {
        $returnsInterface = new class { function foo(): DateTimeInterface { return new DateTime(); } };
        $returnsImmutable = new class { function foo(): DateTimeImmutable { return new DateTimeImmutable(); } };
        $returnsDateTime = new class { function foo(): DateTime { return new DateTime(); } };

        return [
            [true, $returnsInterface, 'foo', DateTimeInterface::class],
            [true, $returnsImmutable, 'foo', DateTimeInterface::class],
            [true, $returnsDateTime, 'foo', DateTimeInterface::class],
            [false, $returnsInterface, 'foo', DateTimeImmutable::class],
            [true, $returnsImmutable, 'foo', DateTimeImmutable::class],
            [false, $returnsDateTime, 'foo', DateTimeImmutable::class],
            [false, $returnsInterface, 'foo', DateTime::class],
            [false, $returnsImmutable, 'foo', DateTime::class],
            [true, $returnsDateTime, 'foo', DateTime::class],
        ];
    }
}
