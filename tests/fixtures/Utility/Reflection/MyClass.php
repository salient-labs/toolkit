<?php declare(strict_types=1);

namespace Lkrms\Tests\Utility\Reflection;

/**
 * MyClass
 */
class MyClass extends MyBaseClass implements MyInterface
{
    use MyTrait, MyReusedTrait;

    public const MY_CONSTANT = 'my constant';

    /**
     * @var int|string
     */
    public $Id;

    /**
     * @var int|null
     */
    public $AltId;

    /**
     * @var string
     */
    public $Name;

    /**
     * @var MyClass|null
     */
    public $Parent;

    /**
     * @var MyClass|null
     */
    public $AltParent;

    /**
     * @param int|string $id
     */
    public function __construct($id, ?int $altId, string $name, ?MyClass $parent, MyClass $altParent = null)
    {
        $this->Id = $id;
        $this->AltId = $altId;
        $this->Name = $name;
        $this->Parent = $parent;
        $this->AltParent = $altParent;
    }

    /**
     * MyClass::$MyDocumentedProperty PHPDoc
     *
     * @var mixed
     */
    public $MyDocumentedProperty;

    /**
     * MyClass::MyDocumentedMethod() PHPDoc
     *
     * @return mixed
     */
    public function MyDocumentedMethod() {}
}
