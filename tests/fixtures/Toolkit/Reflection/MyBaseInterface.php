<?php declare(strict_types=1);

namespace Salient\Tests\Reflection;

/**
 * MyBaseInterface
 */
interface MyBaseInterface
{
    /**
     * MyBaseInterface::MyDocumentedMethod()
     *
     * @return mixed
     */
    public function MyDocumentedMethod();

    /**
     * MyBaseInterface::MySparselyDocumentedMethod()
     *
     * @return mixed
     */
    public function MySparselyDocumentedMethod();
}
