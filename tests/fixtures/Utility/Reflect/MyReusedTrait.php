<?php declare(strict_types=1);

namespace Lkrms\Tests\Utility\Reflect;

/**
 * MyReusedTrait
 */
trait MyReusedTrait
{
    /**
     * MyReusedTrait::$MyDocumentedProperty PHPDoc
     *
     * @var mixed
     */
    public $MyDocumentedProperty;

    /**
     * MyReusedTrait::MyDocumentedMethod() PHPDoc
     *
     * @return mixed
     */
    public function MyDocumentedMethod() {}
}
