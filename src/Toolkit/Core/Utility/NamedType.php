<?php declare(strict_types=1);

namespace Salient\Core\Utility;

use ReflectionNamedType;

/**
 * @internal
 */
class NamedType extends ReflectionNamedType
{
    protected string $Name;
    protected bool $IsBuiltin;
    protected bool $AllowsNull;

    public function __construct(
        string $name,
        bool $isBuiltin = false,
        bool $allowsNull = false
    ) {
        $this->Name = $name;
        $this->IsBuiltin = $isBuiltin;
        $this->AllowsNull = $allowsNull;
    }

    public function getName(): string
    {
        return $this->Name;
    }

    public function isBuiltin(): bool
    {
        return $this->IsBuiltin;
    }

    public function allowsNull(): bool
    {
        return $this->AllowsNull;
    }

    public function __toString(): string
    {
        return $this->Name;
    }
}
