<?php declare(strict_types=1);

namespace Salient\PHPDoc\Tag;

use InvalidArgumentException;

/**
 * @api
 */
class PropertyTag extends AbstractTag
{
    protected bool $IsReadOnly;
    protected bool $IsWriteOnly;

    /**
     * Creates a new PropertyTag object
     *
     * @param class-string|null $class
     */
    public function __construct(
        string $name,
        ?string $type = null,
        bool $isReadOnly = false,
        bool $isWriteOnly = false,
        ?string $description = null,
        ?string $class = null,
        ?string $member = null
    ) {
        if ($isReadOnly && $isWriteOnly) {
            throw new InvalidArgumentException('$isReadOnly and $isWriteOnly cannot both be true');
        }
        parent::__construct('property', $name, $type, $description, $class, $member);
        $this->IsReadOnly = $isReadOnly;
        $this->IsWriteOnly = $isWriteOnly;
    }

    /**
     * @inheritDoc
     */
    public function getName(): string
    {
        return $this->Name;
    }

    /**
     * Check if the property is read-only
     */
    public function isReadOnly(): bool
    {
        return $this->IsReadOnly;
    }

    /**
     * Check if the property is write-only
     */
    public function isWriteOnly(): bool
    {
        return $this->IsWriteOnly;
    }

    /**
     * @inheritDoc
     */
    public function __toString(): string
    {
        $string = "@{$this->Tag}";
        if ($this->IsReadOnly) {
            $string .= '-read';
        } elseif ($this->IsWriteOnly) {
            $string .= '-write';
        }
        if (isset($this->Type)) {
            $string .= " {$this->Type}";
        }
        $string .= " \${$this->Name}";
        if ($this->Description !== null) {
            $string .= " {$this->Description}";
        }
        return $string;
    }
}
