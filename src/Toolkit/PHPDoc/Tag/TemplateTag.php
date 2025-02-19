<?php declare(strict_types=1);

namespace Salient\PHPDoc\Tag;

/**
 * @api
 */
class TemplateTag extends AbstractTag
{
    protected ?string $Default;
    protected bool $IsCovariant;
    protected bool $IsContravariant;

    /**
     * @internal
     */
    public function __construct(
        string $name,
        ?string $type = null,
        ?string $default = null,
        bool $isCovariant = false,
        bool $isContravariant = false,
        ?string $class = null,
        ?string $member = null,
        ?string $static = null,
        ?string $self = null,
        array $aliases = []
    ) {
        if ($isCovariant && $isContravariant) {
            $this->throw('$isCovariant and $isContravariant cannot both be true');
        }
        parent::__construct('template', $name, $type, null, $class, $member, $static, $self, $aliases);
        $this->Default = $this->filterType($default, $aliases);
        $this->IsCovariant = $isCovariant;
        $this->IsContravariant = $isContravariant;
    }

    /**
     * @inheritDoc
     */
    public function getName(): string
    {
        return $this->Name;
    }

    /**
     * @return null
     */
    public function getDescription(): ?string
    {
        return null;
    }

    /**
     * Get the default value of the template
     */
    public function getDefault(): ?string
    {
        return $this->Default;
    }

    /**
     * Check if the template is covariant
     */
    public function isCovariant(): bool
    {
        return $this->IsCovariant;
    }

    /**
     * Check if the template is contravariant
     */
    public function isContravariant(): bool
    {
        return $this->IsContravariant;
    }

    /**
     * Get an instance with the given name
     *
     * @return static
     */
    public function withName(string $name)
    {
        return $this->with('Name', $this->filterString($name, 'name'));
    }

    /**
     * Get an instance with the given PHPDoc type
     *
     * @return static
     */
    public function withType(?string $type)
    {
        if ($type === null) {
            return $this->without('Type');
        }
        return $this->with('Type', $this->filterType($type));
    }

    /**
     * @inheritDoc
     */
    public function withDescription(?string $description)
    {
        if ($description !== null) {
            $this->throw('Invalid description');
        }
        return $this;
    }

    /**
     * Get an instance with the given default value
     *
     * @return static
     */
    public function withDefault(?string $default)
    {
        return $this->with('Default', $this->filterType($default));
    }

    /**
     * Get an instance without covariance or contravariance
     *
     * @return static
     */
    public function withoutVariance()
    {
        return $this
            ->with('IsCovariant', false)
            ->with('IsContravariant', false);
    }

    /**
     * @inheritDoc
     */
    public function __toString(): string
    {
        $string = "@{$this->Tag}";
        if ($this->IsCovariant) {
            $string .= '-covariant';
        } elseif ($this->IsContravariant) {
            $string .= '-contravariant';
        }
        $string .= " {$this->Name}";
        if (isset($this->Type)) {
            $string .= " of {$this->Type}";
        }
        if ($this->Default !== null) {
            $string .= " = {$this->Default}";
        }
        return $string;
    }
}
