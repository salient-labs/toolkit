<?php declare(strict_types=1);

namespace Salient\PHPDoc\Tag;

/**
 * @api
 */
class VarTag extends AbstractTag
{
    /**
     * @internal
     */
    public function __construct(
        string $type,
        ?string $name = null,
        ?string $description = null,
        ?string $class = null,
        ?string $member = null,
        ?string $static = null,
        ?string $self = null,
        array $aliases = []
    ) {
        parent::__construct('var', $name, $type, $description, $class, $member, $static, $self, $aliases);
    }

    /**
     * @inheritDoc
     */
    public function getType(): string
    {
        return $this->Type;
    }

    /**
     * Get an instance with the given name
     *
     * @return static
     */
    public function withName(?string $name)
    {
        if ($name === null) {
            return $this->without('Name');
        }
        return $this->with('Name', $this->filterString($name, 'name'));
    }

    /**
     * @inheritDoc
     */
    public function __toString(): string
    {
        $string = "@{$this->Tag} {$this->Type}";
        if (isset($this->Name)) {
            $string .= " \${$this->Name}";
        }
        if ($this->Description !== null) {
            $string .= " {$this->Description}";
        }
        return $string;
    }
}
