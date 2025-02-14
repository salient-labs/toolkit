<?php declare(strict_types=1);

namespace Salient\PHPDoc\Tag;

/**
 * @api
 */
class ReturnTag extends AbstractTag
{
    /**
     * @internal
     */
    public function __construct(
        string $type,
        ?string $description = null,
        ?string $class = null,
        ?string $member = null,
        array $aliases = []
    ) {
        parent::__construct('return', null, $type, $description, $class, $member, $aliases);
    }

    /**
     * @inheritDoc
     */
    public function getType(): string
    {
        return $this->Type;
    }

    /**
     * @inheritDoc
     */
    public function __toString(): string
    {
        $string = "@{$this->Tag} {$this->Type}";
        if ($this->Description !== null) {
            $string .= " {$this->Description}";
        }
        return $string;
    }
}
