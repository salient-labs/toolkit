<?php declare(strict_types=1);

namespace Salient\Tests\Reflection;

/**
 * MyBaseTrait
 */
trait MyBaseTrait
{
    use MyReusedTrait;

    /**
     * MyBaseTrait::$MyDocumentedProperty PHPDoc
     *
     * @var mixed
     */
    public $MyDocumentedProperty;

    /**
     * MyBaseTrait::MyDocumentedMethod() PHPDoc
     *
     * @return mixed
     */
    public function MyDocumentedMethod() {}

    /**
     * MyBaseTrait::MySparselyDocumentedMethod() PHPDoc
     *
     * @return mixed
     */
    public function MySparselyDocumentedMethod() {}

    /**
     * MyBaseTrait::Adaptable() PHPDoc
     *
     * @return mixed
     */
    private function Adaptable() {}
}
