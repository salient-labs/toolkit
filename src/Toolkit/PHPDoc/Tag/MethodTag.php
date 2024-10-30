<?php declare(strict_types=1);

namespace Salient\PHPDoc\Tag;

use Salient\Utility\Arr;

/**
 * @api
 */
class MethodTag extends AbstractTag
{
    protected bool $IsStatic;
    /** @var array<string,MethodParam> */
    protected array $Params;

    /**
     * Creates a new MethodTag object
     *
     * @param array<string,MethodParam> $params
     * @param class-string|null $class
     */
    public function __construct(
        string $name,
        bool $isStatic = false,
        ?string $type = null,
        array $params = [],
        ?string $description = null,
        ?string $class = null,
        ?string $member = null
    ) {
        parent::__construct('method', $name, $type, $description, $class, $member);
        $this->IsStatic = $isStatic;
        $this->Params = $params;
    }

    /**
     * @inheritDoc
     */
    public function getName(): string
    {
        return $this->Name;
    }

    /**
     * Check if the method is static
     */
    public function isStatic(): bool
    {
        return $this->IsStatic;
    }

    /**
     * Get the method's parameters, indexed by name
     *
     * @return array<string,MethodParam>
     */
    public function getParams(): array
    {
        return $this->Params;
    }

    /**
     * @inheritDoc
     */
    public function __toString(): string
    {
        $string = "@{$this->Tag} ";
        if ($this->IsStatic) {
            $string .= 'static ';
        }
        if (isset($this->Type)) {
            $string .= "{$this->Type} ";
        }
        $string .= "{$this->Name}(";
        $string .= Arr::implode(', ', $this->Params, '');
        $string .= ')';
        if ($this->Description !== null) {
            $string .= " {$this->Description}";
        }
        return $string;
    }
}
