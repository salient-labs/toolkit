<?php declare(strict_types=1);

namespace Salient\Tests\Reflection;

/**
 * MyInterface
 */
interface MyInterface extends MyBaseInterface
{
    /**
     * MyInterface::MyDocumentedMethod()
     *
     * @return mixed
     */
    public function MyDocumentedMethod();
}
