<?php declare(strict_types=1);

namespace Salient\PHPDoc\Tag;

/**
 * @api
 */
class GenericTag extends AbstractTag
{
    /**
     * Creates a new GenericTag object
     *
     * @param class-string|null $class
     */
    public function __construct(
        string $tag,
        ?string $description,
        ?string $class = null,
        ?string $member = null
    ) {
        parent::__construct($tag, null, null, $description, $class, $member);
    }

    /**
     * @return null
     */
    public function getName(): ?string
    {
        return null;
    }

    /**
     * @return null
     */
    public function getType(): ?string
    {
        return null;
    }

    /**
     * @inheritDoc
     */
    public function __toString(): string
    {
        $string = "@{$this->Tag}";
        if ($this->Description !== null) {
            $string .= " {$this->Description}";
        }
        return $string;
    }
}