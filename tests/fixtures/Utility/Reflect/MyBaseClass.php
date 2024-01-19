<?php declare(strict_types=1);

namespace Lkrms\Tests\Utility\Reflect;

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
     * @phpstan-ignore-next-line
     */
    private $MyPrivateProperty1;

    /**
     * @var mixed
     * @phpstan-ignore-next-line
     */
    private $MyPrivateProperty2;

    /**
     * MyBaseClass::MyDocumentedMethod() PHPDoc
     *
     * @return mixed
     */
    public function MyDocumentedMethod() {}
}
