<?php declare(strict_types=1);

namespace Lkrms\Tests\Utility\Reflection;

trait MyBaseTrait
{
    /**
     * MyBaseTrait::$MyDocumentedProperty PHPDoc
     */
    public $MyDocumentedProperty;

    /**
     * MyBaseTrait::MyDocumentedMethod() PHPDoc
     */
    public function MyDocumentedMethod()
    {
    }
}
