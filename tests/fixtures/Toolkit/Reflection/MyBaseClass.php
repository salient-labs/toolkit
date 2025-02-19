<?php declare(strict_types=1);

namespace Salient\Tests\Reflection;

/**
 * MyBaseClass
 */
abstract class MyBaseClass
{
    /**
     * MyBaseClass::MY_CONSTANT
     */
    public const MY_CONSTANT = 'my constant';

    /**
     * MyBaseClass::$MyDocumentedProperty
     *
     * @var mixed
     */
    public $MyDocumentedProperty;

    /**
     * @var mixed
     * @phpstan-ignore property.unused
     */
    private $MyPrivateProperty1;

    /**
     * MyBaseClass::$MyPrivateProperty2
     *
     * @var mixed
     */
    private $MyPrivateProperty2;

    /**
     * MyBaseClass::MyDocumentedMethod()
     *
     * @return mixed
     */
    public function MyDocumentedMethod()
    {
        if ($this->MyPrivateProperty2) {
            $this->MyPrivateProperty2 = null;
        }
        $this->MyPrivateMethod();
    }

    /**
     * MyBaseClass::MyPrivateMethod()
     */
    private function MyPrivateMethod(): void {}
}
