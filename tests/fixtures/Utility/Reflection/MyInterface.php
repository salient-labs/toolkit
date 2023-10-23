<?php declare(strict_types=1);

namespace Lkrms\Tests\Utility\Reflection;

/**
 * MyInterface
 */
interface MyInterface extends MyBaseInterface
{
    /**
     * MyInterface::MyDocumentedMethod() PHPDoc
     *
     * @return mixed
     */
    public function MyDocumentedMethod();
}
