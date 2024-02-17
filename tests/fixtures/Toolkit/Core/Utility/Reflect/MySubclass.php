<?php declare(strict_types=1);

namespace Salient\Tests\Core\Utility\Reflect;

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
