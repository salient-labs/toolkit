<?php declare(strict_types=1);

namespace Salient\Tests\Reflection;

class MyUndocumentedClass extends MyClass
{
    /**
     * MyUndocumentedClass::MyDocumentedMethod() PHPDoc
     *
     * @return mixed
     */
    public function MyDocumentedMethod() {}
}
