<?php declare(strict_types=1);

namespace Salient\Tests\Reflection;

/**
 * MySubclass
 */
class MySubclass extends MyUndocumentedClass implements MyOtherInterface
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
