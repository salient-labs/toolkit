<?php declare(strict_types=1);

namespace Salient\Tests\Reflection;

/**
 * MyBaseInterface
 */
interface MyBaseInterface
{
    /**
     * MyBaseInterface::MyDocumentedMethod() PHPDoc
     *
     * @return mixed
     */
    public function MyDocumentedMethod();

    /**
     * MyBaseInterface::MySparselyDocumentedMethod() PHPDoc
     *
     * @return mixed
     */
    public function MySparselyDocumentedMethod();
}
