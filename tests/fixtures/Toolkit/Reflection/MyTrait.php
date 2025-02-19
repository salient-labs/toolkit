<?php declare(strict_types=1);

namespace Salient\Tests\Reflection;

/**
 * MyTrait
 */
trait MyTrait
{
    use MyBaseTrait;

    /**
     * MyTrait::$MyDocumentedProperty
     *
     * @var mixed
     */
    public $MyDocumentedProperty;

    /**
     * MyTrait::$MyPrivateProperty2
     *
     * @var mixed
     */
    protected $MyPrivateProperty2;

    /**
     * MyTrait::MyDocumentedMethod()
     *
     * @return mixed
     */
    public function MyDocumentedMethod() {}

    /**
     * MyTrait::MyTraitOnlyMethod()
     */
    public function MyTraitOnlyMethod(): void {}

    /**
     * MyTrait::MyPrivateMethod()
     */
    private function MyPrivateMethod(): void {}
}
