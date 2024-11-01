<?php declare(strict_types=1);

namespace Salient\Tests\Sli\Command\AnalyseClass;

/**
 * Summary of MyClass
 *
 * Extended description of `MyClass`.
 *
 * @template T of int|string
 */
final class MyClass extends MyBaseClass
{
    use MyTrait;

    protected const MY_FLOAT = 3.0;

    /**
     * Summary of MyClass::$MyProperty
     *
     * Extended description of `MyClass::$MyProperty`.
     *
     * @var T
     */
    public $MyProperty;

    // @phpstan-ignore property.unused, missingType.property
    private static $MyStaticProperty;

    // @phpstan-ignore property.onlyWritten, missingType.property
    private static $MyStaticPropertyWithDefault = 0;

    // @phpstan-ignore property.unused
    private static int $MyStaticTypedProperty;

    // @phpstan-ignore property.unused
    private static ?int $MyNullableStaticTypedProperty;

    // @phpstan-ignore property.onlyWritten
    private static ?int $MyNullableStaticTypedPropertyWithDefault = null;

    /**
     * @api
     *
     * @param T $myProperty
     */
    public function __construct($myProperty)
    {
        $this->MyProperty = $myProperty;
    }

    /**
     * Summary of MyClass::MyTemplateMethod()
     *
     * @template TInstance of MyInterface
     *
     * @param TInstance $instance
     * @param T|null $myProperty
     * @return array<T,TInstance>
     */
    protected function MyTemplateMethod(MyInterface $instance, $myProperty = null): array
    {
        return [$myProperty ?? $this->MyProperty => $instance];
    }

    public function __toString(): string
    {
        return (string) $this->MyProperty;
    }
}
