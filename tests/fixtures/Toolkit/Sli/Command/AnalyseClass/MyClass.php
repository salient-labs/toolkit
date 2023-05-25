<?php declare(strict_types=1);

namespace Salient\Tests\Sli\Command\AnalyseClass;

/**
 * MyClass
 *
 * @template T of int|string
 */
final class MyClass extends MyBaseClass
{
    use MyTrait;

    /**
     * MyClass::$MyProperty
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
     * MyClass::MyTemplateMethod()
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
