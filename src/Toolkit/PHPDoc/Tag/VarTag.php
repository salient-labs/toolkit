<?php declare(strict_types=1);

namespace Salient\PHPDoc\Tag;

/**
 * A "@var" tag
 */
class VarTag extends AbstractTag
{
    /**
     * Creates a new VarTag object
     *
     * @param class-string|null $class
     */
    public function __construct(
        string $type,
        ?string $name = null,
        ?string $description = null,
        ?string $class = null,
        ?string $member = null
    ) {
        parent::__construct('var', $name, $type, $description, $class, $member);
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
}
