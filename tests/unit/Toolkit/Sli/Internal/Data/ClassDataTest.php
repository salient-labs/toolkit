<?php declare(strict_types=1);

namespace Salient\Tests\Sli\Internal\Data;

use Salient\Sli\Internal\Data\ClassData;
use Salient\Sli\Internal\TokenExtractor;
use Salient\Tests\Reflection\MyBackedEnum;
use Salient\Tests\Reflection\MyBackedEnumTrait;
use Salient\Tests\Reflection\MyBaseClass;
use Salient\Tests\Reflection\MyBaseInterface;
use Salient\Tests\Reflection\MyBaseTrait;
use Salient\Tests\Reflection\MyClass;
use Salient\Tests\Reflection\MyInterface;
use Salient\Tests\Reflection\MyReusedTrait;
use Salient\Tests\Reflection\MyTrait;
use Salient\Tests\TestCase;
use Salient\Utility\Arr;
use ReflectionClass;

/**
 * @covers \Salient\Sli\Internal\Data\ClassData
 * @covers \Salient\Sli\Internal\Data\ConstantData
 * @covers \Salient\Sli\Internal\Data\MethodData
 * @covers \Salient\Sli\Internal\Data\PropertyData
 * @covers \Salient\Sli\Internal\TokenExtractor
 */
final class ClassDataTest extends TestCase
{
    public function testFromExtractor(): void
    {
        $data = self::getClassDataFromExtractor(MyInterface::class);
        $this->assertSame('MyInterface', $data->Name);
        $this->assertSame('Salient\Tests\Reflection', $data->Namespace);
        $this->assertSame('interface', $data->Type);
        $this->assertSame([MyBaseInterface::class], $data->Extends);
        $this->assertSame([], $data->Implements);
        $this->assertSame([], $data->Uses);

        $data = self::getClassDataFromExtractor(MyClass::class);
        $this->assertSame('MyClass', $data->Name);
        $this->assertSame('Salient\Tests\Reflection', $data->Namespace);
        $this->assertSame('class', $data->Type);
        $this->assertSame([MyBaseClass::class], $data->Extends);
        $this->assertSame([MyInterface::class], $data->Implements);
        $this->assertSame([MyReusedTrait::class, MyTrait::class], Arr::sort($data->Uses));

        $data = self::getClassDataFromExtractor(MyTrait::class);
        $this->assertSame('MyTrait', $data->Name);
        $this->assertSame('Salient\Tests\Reflection', $data->Namespace);
        $this->assertSame('trait', $data->Type);
        $this->assertSame([], $data->Extends);
        $this->assertSame([], $data->Implements);
        $this->assertSame([MyBaseTrait::class], $data->Uses);

        if (\PHP_VERSION_ID >= 80100) {
            $data = self::getClassDataFromExtractor(MyBackedEnum::class);
            $this->assertSame('MyBackedEnum', $data->Name);
            $this->assertSame('Salient\Tests\Reflection', $data->Namespace);
            $this->assertSame('enum', $data->Type);
            $this->assertSame([], $data->Extends);
            $this->assertSame([MyInterface::class], $data->Implements);
            $this->assertSame([MyBackedEnumTrait::class], $data->Uses);
        }
    }

    public function testFromReflection(): void
    {
        $data = ClassData::fromReflection(new ReflectionClass(MyInterface::class));
        $this->assertSame('MyInterface', $data->Name);
        $this->assertSame('Salient\Tests\Reflection', $data->Namespace);
        $this->assertSame('interface', $data->Type);
        $this->assertSame([MyBaseInterface::class], $data->Extends);
        $this->assertSame([], $data->Implements);
        $this->assertSame([], $data->Uses);

        $data = ClassData::fromReflection(new ReflectionClass(MyClass::class));
        $this->assertSame('MyClass', $data->Name);
        $this->assertSame('Salient\Tests\Reflection', $data->Namespace);
        $this->assertSame('class', $data->Type);
        $this->assertSame([MyBaseClass::class], $data->Extends);
        $this->assertSame([MyBaseInterface::class, MyInterface::class], Arr::sort($data->Implements));
        $this->assertSame([MyReusedTrait::class, MyTrait::class], Arr::sort($data->Uses));

        $data = ClassData::fromReflection(new ReflectionClass(MyTrait::class));
        $this->assertSame('MyTrait', $data->Name);
        $this->assertSame('Salient\Tests\Reflection', $data->Namespace);
        $this->assertSame('trait', $data->Type);
        $this->assertSame([], $data->Extends);
        $this->assertSame([], $data->Implements);
        $this->assertSame([MyBaseTrait::class], $data->Uses);

        if (\PHP_VERSION_ID >= 80100) {
            $data = ClassData::fromReflection(new ReflectionClass(MyBackedEnum::class));
            $this->assertSame('MyBackedEnum', $data->Name);
            $this->assertSame('Salient\Tests\Reflection', $data->Namespace);
            $this->assertSame('enum', $data->Type);
            $this->assertSame([], $data->Extends);
            $this->assertSame([MyBaseInterface::class, MyInterface::class], Arr::sort($data->Implements));
            $this->assertSame([MyBackedEnumTrait::class], $data->Uses);
        }
    }

    /**
     * @param class-string $class
     */
    private static function getClassDataFromExtractor(string $class): ClassData
    {
        $class = new ReflectionClass($class);
        return ClassData::fromExtractor(TokenExtractor::forClass($class), $class);
    }
}
