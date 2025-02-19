<?php declare(strict_types=1);

namespace Salient\Tests\Reflection;

/**
 * MyBaseTrait
 */
trait MyBaseTrait
{
    use MyReusedTrait;

    /**
     * MyBaseTrait::$MyDocumentedProperty
     *
     * @var mixed
     */
    public $MyDocumentedProperty;

    /**
     * MyBaseTrait::MyDocumentedMethod()
     *
     * @return mixed
     */
    public function MyDocumentedMethod() {}

    /**
     * MyBaseTrait::MySparselyDocumentedMethod()
     *
     * @return mixed
     */
    public function MySparselyDocumentedMethod() {}

    /**
     * MyBaseTrait::MyPrivateMethod()
     */
    private function MyPrivateMethod(): void {}

    /**
     * MyBaseTrait::Adaptable()
     *
     * @return mixed
     */
    private function Adaptable() {}
}
