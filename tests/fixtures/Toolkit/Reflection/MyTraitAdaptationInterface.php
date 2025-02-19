<?php declare(strict_types=1);

namespace Salient\Tests\Reflection;

/**
 * MyTraitAdaptationInterface
 */
interface MyTraitAdaptationInterface
{
    /**
     * MyTraitAdaptationInterface::MyDocumentedMethod()
     *
     * @return mixed
     */
    public function MyDocumentedMethod();

    /**
     * MyTraitAdaptationInterface::MyAdaptableMethod()
     *
     * @return mixed
     */
    public function MyAdaptableMethod();
}
