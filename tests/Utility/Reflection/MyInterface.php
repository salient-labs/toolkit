<?php

declare(strict_types=1);

namespace Lkrms\Tests\Utility\Reflection;

interface MyInterface extends MyBaseInterface
{
    /**
     * MyInterface::MyDocumentedMethod() PHPDoc
     */
    public function MyDocumentedMethod();

}