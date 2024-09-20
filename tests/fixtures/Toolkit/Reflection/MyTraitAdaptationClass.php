<?php declare(strict_types=1);

namespace Salient\Tests\Reflection;

/**
 * MyTraitAdaptationClass
 */
class MyTraitAdaptationClass implements MyTraitAdaptationInterface, MyInterface
{
    use MyBaseTrait {
        Adaptable as public MyAdaptableMethod;
    }

    /**
     * MyTraitAdaptationClass::MyDocumentedMethod() PHPDoc
     *
     * @return mixed
     */
    public function MyDocumentedMethod() {}

    /**
     * MyTraitAdaptationClass::Adaptable() PHPDoc
     *
     * @return mixed
     */
    protected function Adaptable() {}
}
