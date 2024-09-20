<?php declare(strict_types=1);

namespace Salient\Tests\Reflection;

/**
 * MyTraitAdaptationInterface
 */
interface MyTraitAdaptationInterface
{
    /**
     * MyTraitAdaptationInterface::MyDocumentedMethod() PHPDoc
     *
     * @return mixed
     */
    public function MyDocumentedMethod();

    /**
     * MyTraitAdaptationInterface::MyAdaptableMethod() PHPDoc
     *
     * @return mixed
     */
    public function MyAdaptableMethod();
}
