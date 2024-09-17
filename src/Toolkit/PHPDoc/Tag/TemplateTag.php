<?php declare(strict_types=1);

namespace Salient\PHPDoc\Tag;

use Salient\Core\Concern\HasMutator;
use Stringable;

/**
 * A "@template" tag
 */
class TemplateTag extends AbstractTag implements Stringable
{
    use HasMutator;

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
        ?string $variance = null,
        ?string $class = null,
        ?string $member = null
    ) {
        parent::__construct('template', $name, $type, null, $class, $member);
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
     * Get an instance with the given variance
     *
     * @param "covariant"|"contravariant"|null $variance
     * @return static
     */
    public function withVariance(?string $variance)
    {
        return $this->with('Variance', $this->filterVariance($variance));
    }

    public function __toString(): string
    {
        return sprintf(
            '@template%s %s%s',
            $this->Variance === null ? '' : '-' . $this->Variance,
            $this->Name,
            !isset($this->Type) ? '' : ' of ' . $this->Type
        );
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
        $this->throw("Invalid variance '%s'", $variance);
    }
}
