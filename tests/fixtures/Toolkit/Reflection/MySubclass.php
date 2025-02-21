<?php declare(strict_types=1);

namespace Salient\Tests\Reflection;

/**
 * MySubclass
 */
class MySubclass extends MyUndocumentedClass implements MyOtherInterface
{
    /**
     * MySubclass::$MyDocumentedProperty
     *
     * @var mixed
     */
    public $MyDocumentedProperty;

    /**
     * MySubclass::MyDocumentedMethod()
     *
     * @return mixed
     */
    public function MyDocumentedMethod() {}
}
