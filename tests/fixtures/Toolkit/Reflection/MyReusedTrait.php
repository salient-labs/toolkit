<?php declare(strict_types=1);

namespace Salient\Tests\Reflection;

/**
 * MyReusedTrait
 */
trait MyReusedTrait
{
    /**
     * MyReusedTrait::$MyDocumentedProperty
     *
     * @var mixed
     */
    public $MyDocumentedProperty;

    /**
     * MyReusedTrait::MyDocumentedMethod()
     *
     * @return mixed
     */
    public function MyDocumentedMethod() {}

    /**
     * MyReusedTrait::MyPrivateMethod()
     */
    private function MyPrivateMethod(): void {}
}
