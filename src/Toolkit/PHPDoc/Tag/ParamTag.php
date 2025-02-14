<?php declare(strict_types=1);

namespace Salient\PHPDoc\Tag;

/**
 * @api
 */
class ParamTag extends AbstractTag
{
    protected bool $IsPassedByReference;
    protected bool $IsVariadic;

    /**
     * @internal
     */
    public function __construct(
        string $name,
        ?string $type = null,
        bool $isPassedByReference = false,
        bool $isVariadic = false,
        ?string $description = null,
        ?string $class = null,
        ?string $member = null,
        array $aliases = []
    ) {
        parent::__construct('param', $name, $type, $description, $class, $member, $aliases);
        $this->IsPassedByReference = $isPassedByReference;
        $this->IsVariadic = $isVariadic;
    }

    /**
     * @inheritDoc
     */
    public function getName(): string
    {
        return $this->Name;
    }

    /**
     * Check if the parameter is passed by reference
     */
    public function isPassedByReference(): bool
    {
        return $this->IsPassedByReference;
    }

    /**
     * Check if the parameter is variadic
     */
    public function isVariadic(): bool
    {
        return $this->IsVariadic;
    }

    /**
     * @inheritDoc
     */
    public function __toString(): string
    {
        $string = "@{$this->Tag} ";
        if (isset($this->Type)) {
            $string .= "{$this->Type} ";
        }
        if ($this->IsPassedByReference) {
            $string .= '&';
        }
        if ($this->IsVariadic) {
            $string .= '...';
        }
        $string .= "\${$this->Name}";
        if ($this->Description !== null) {
            $string .= " {$this->Description}";
        }
        return $string;
    }
}
