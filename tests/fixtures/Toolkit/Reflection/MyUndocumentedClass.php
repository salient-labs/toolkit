<?php declare(strict_types=1);

namespace Salient\Tests\Reflection;

class MyUndocumentedClass extends MyClass
{
    /**
     * MyUndocumentedClass::MyDocumentedMethod()
     *
     * @return mixed
     */
    public function MyDocumentedMethod() {}

    /**
     * MyUndocumentedClass::MyPrivateMethod()
     */
    protected function MyPrivateMethod(): void {}
}
