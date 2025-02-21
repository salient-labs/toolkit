<?php declare(strict_types=1);

namespace Salient\Tests\Reflection;

/**
 * MyClass
 */
class MyClass extends MyBaseClass implements MyInterface
{
    use MyTrait;
    use MyReusedTrait;

    /**
     * MyClass::MY_CONSTANT
     */
    public const MY_CONSTANT = 'my constant';

    /** @var int|string */
    public $Id;
    /** @var int|null */
    public $AltId;
    /** @var string */
    public $Name;
    /** @var MyClass|null */
    public $Parent;
    /** @var MyClass|null */
    public $AltParent;

    /**
     * MyClass::$MyPrivateProperty2
     *
     * @var mixed
     */
    protected $MyPrivateProperty2;

    /**
     * @param int|string $id
     */
    public function __construct($id, ?int $altId, string $name, ?MyClass $parent, ?MyClass $altParent = null)
    {
        $this->Id = $id;
        $this->AltId = $altId;
        $this->Name = $name;
        $this->Parent = $parent;
        $this->AltParent = $altParent;
    }

    /**
     * MyClass::$MyDocumentedProperty
     *
     * @var mixed
     */
    public $MyDocumentedProperty;

    /**
     * MyClass::MyDocumentedMethod()
     *
     * @return mixed
     */
    public function MyDocumentedMethod()
    {
        $this->MyPrivateMethod();
    }

    public function MySparselyDocumentedMethod() {}

    /**
     * MyClass::MyPrivateMethod()
     */
    private function MyPrivateMethod(): void {}
}
