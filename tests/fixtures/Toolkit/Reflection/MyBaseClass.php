<?php declare(strict_types=1);

namespace Salient\Tests\Reflection;

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
     * @var mixed
     * @phpstan-ignore property.unused
     */
    private $MyPrivateProperty1;

    /**
     * @var mixed
     * @phpstan-ignore property.unused
     */
    private $MyPrivateProperty2;

    /**
     * MyBaseClass::MyDocumentedMethod() PHPDoc
     *
     * @return mixed
     */
    public function MyDocumentedMethod() {}
}
