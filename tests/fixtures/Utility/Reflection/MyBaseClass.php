<?php declare(strict_types=1);

namespace Lkrms\Tests\Utility\Reflection;

/**
 * MyBaseClass
 */
abstract class MyBaseClass
{
    /**
     * MyBaseClass::$MyDocumentedProperty PHPDoc
     *
     * @var mixed
     */
    public $MyDocumentedProperty;

    /**
     * MyBaseClass::MyDocumentedMethod() PHPDoc
     *
     * @return mixed
     */
    public function MyDocumentedMethod() {}
}
