<?php declare(strict_types=1);

namespace Salient\PHPDoc\Tag;

use Stringable;

/**
 * @api
 */
class MethodParam implements Stringable
{
    protected string $Name;
    protected ?string $Type;
    protected ?string $Default;
    protected bool $IsVariadic;

    /**
     * Creates a new MethodParam object
     */
    public function __construct(
        string $name,
        ?string $type = null,
        ?string $default = null,
        bool $isVariadic = false
    ) {
        $this->Name = $name;
        $this->Type = $type;
        $this->Default = $default;
        $this->IsVariadic = $isVariadic;
    }

    /**
     * Get the name of the parameter
     */
    public function getName(): string
    {
        return $this->Name;
    }

    /**
     * Get the PHPDoc type of the parameter
     */
    public function getType(): ?string
    {
        return $this->Type;
    }

    /**
     * Get the default value of the parameter
     */
    public function getDefault(): ?string
    {
        return $this->Default;
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
        $string = '';
        if ($this->Type !== null) {
            $string .= "{$this->Type} ";
        }
        if ($this->IsVariadic) {
            $string .= '...';
        }
        $string .= "\${$this->Name}";
        if ($this->Default !== null) {
            $string .= " = {$this->Default}";
        }
        return $string;
    }
}
