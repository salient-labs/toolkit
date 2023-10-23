<?php declare(strict_types=1);

namespace Lkrms\Tests\Utility\Reflection;

/**
 * MySubclass
 */
class MySubclass extends MyClass implements MyOtherInterface
{
    /**
     * MySubclass::$MyDocumentedProperty PHPDoc
     *
     * @var mixed
     */
    public $MyDocumentedProperty;

    /**
     * MySubclass::MyDocumentedMethod() PHPDoc
     *
     * @return mixed
     */
    public function MyDocumentedMethod() {}
}
