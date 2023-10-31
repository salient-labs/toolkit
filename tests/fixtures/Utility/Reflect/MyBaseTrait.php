<?php declare(strict_types=1);

namespace Lkrms\Tests\Utility\Reflect;

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
}
