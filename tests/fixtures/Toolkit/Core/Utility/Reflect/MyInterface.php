<?php declare(strict_types=1);

namespace Salient\Tests\Core\Utility\Reflect;

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
