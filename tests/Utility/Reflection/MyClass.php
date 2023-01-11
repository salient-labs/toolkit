<?php declare(strict_types=1);

namespace Lkrms\Tests\Utility\Reflection;

use ArrayAccess;
use Countable;

/**
 * MyClass
 */
class MyClass extends MyBaseClass implements MyInterface
{
    use MyTrait, MyReusedTrait;

    public const MY_CONSTANT = 'my constant';

    public $Id;

    public $AltId;

    public $Name;

    public $Parent;

    public $AltParent;

    public function __construct($id, ?int $altId, string $name, ?MyClass $parent, MyClass $altParent = null)
    {
        $this->Id        = $id;
        $this->AltId     = $altId;
        $this->Name      = $name;
        $this->Parent    = $parent;
        $this->AltParent = $altParent;
    }

    public function MyMethod($mixed, ?int $nullableInt, string $string, Countable&ArrayAccess $intersection, MyBaseClass $class, ?MyClass $nullableClass, ?MyClass &$nullableClassByRef, ?MyClass $nullableAndOptionalClass = null, string $optionalString = MyClass::MY_CONSTANT, string|MyClass $union = SELF::MY_CONSTANT, string|MyClass|null $nullableUnion = 'literal', array|MyClass $optionalArrayUnion = ['key' => 'value'], string|MyClass|null &$nullableUnionByRef = null, string&...$variadicByRef): MyClass | string | null
    {
        return null;
    }

    /**
     * MyClass::$MyDocumentedProperty PHPDoc
     */
    public $MyDocumentedProperty;

    /**
     * MyClass::MyDocumentedMethod() PHPDoc
     */
    public function MyDocumentedMethod()
    {
    }
}
