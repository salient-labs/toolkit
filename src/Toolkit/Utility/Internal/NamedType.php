<?php declare(strict_types=1);

namespace Salient\Utility\Internal;

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

    /**
     * @inheritDoc
     */
    public function getName(): string
    {
        return $this->Name;
    }

    /**
     * @inheritDoc
     */
    public function isBuiltin(): bool
    {
        return $this->IsBuiltin;
    }

    /**
     * @inheritDoc
     */
    public function allowsNull(): bool
    {
        return $this->AllowsNull;
    }

    /**
     * @inheritDoc
     */
    public function __toString(): string
    {
        return $this->Name;
    }
}
