<?php

declare(strict_types=1);

namespace Lkrms\Tests\Utility\Reflection;

class MySubclass extends MyClass implements MyOtherInterface
{
    /**
     * MySubclass::$MyDocumentedProperty PHPDoc
     */
    public $MyDocumentedProperty;

    /**
     * MySubclass::MyDocumentedMethod() PHPDoc
     */
    public function MyDocumentedMethod()
    {
    }

}
