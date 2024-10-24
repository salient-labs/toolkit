<?php declare(strict_types=1);

namespace Salient\Tests\Sli\Command\AnalyseClass;

/**
 * Summary of MyClass
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
     * @var T
     */
    public $MyProperty;

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
