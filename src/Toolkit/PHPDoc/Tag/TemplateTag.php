<?php declare(strict_types=1);

namespace Salient\PHPDoc\Tag;

/**
 * @api
 */
class TemplateTag extends AbstractTag
{
    protected ?string $Default;
    /** @var "covariant"|"contravariant"|null */
    protected ?string $Variance;

    /**
     * Creates a new TemplateTag object
     *
     * @param "covariant"|"contravariant"|null $variance
     * @param class-string|null $class
     */
    public function __construct(
        string $name,
        ?string $type = null,
        ?string $default = null,
        ?string $variance = null,
        ?string $class = null,
        ?string $member = null
    ) {
        parent::__construct('template', $name, $type, null, $class, $member);
        $this->Default = $this->filterType($default);
        $this->Variance = $this->filterVariance($variance);
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
     * Get the variance of the template
     *
     * @return "covariant"|"contravariant"|null
     */
    public function getVariance(): ?string
    {
        return $this->Variance;
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
     * Get an instance with the given variance
     *
     * @param "covariant"|"contravariant"|null $variance
     * @return static
     */
    public function withVariance(?string $variance)
    {
        return $this->with('Variance', $this->filterVariance($variance));
    }

    /**
     * @inheritDoc
     */
    public function __toString(): string
    {
        $string = "@{$this->Tag}";
        if ($this->Variance !== null) {
            $string .= "-{$this->Variance}";
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

    /**
     * @return "covariant"|"contravariant"|null
     */
    protected function filterVariance(?string $variance): ?string
    {
        if (
            $variance === null
            || $variance === 'covariant'
            || $variance === 'contravariant'
        ) {
            return $variance;
        }
        // @codeCoverageIgnoreStart
        $this->throw("Invalid variance '%s'", $variance);
        // @codeCoverageIgnoreEnd
    }
}
