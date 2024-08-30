<?php declare(strict_types=1);

namespace Salient\Tests\Reflection;

/**
 * MyTrait
 */
trait MyTrait
{
    use MyBaseTrait;

    /**
     * MyTrait::$MyDocumentedProperty PHPDoc
     *
     * @var mixed
     */
    public $MyDocumentedProperty;

    /**
     * MyTrait::MyDocumentedMethod() PHPDoc
     *
     * @return mixed
     */
    public function MyDocumentedMethod() {}

    /**
     * MyTrait::MyTraitOnlyMethod() PHPDoc
     */
    public function MyTraitOnlyMethod(): void {}
}
