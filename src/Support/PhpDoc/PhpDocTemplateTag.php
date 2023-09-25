<?php declare(strict_types=1);

namespace Lkrms\Support\PhpDoc;

use UnexpectedValueException;

/**
 * A "template" tag extracted from a PHP DocBlock
 */
class PhpDocTemplateTag extends PhpDocTag
{
    /**
     * @var string
     */
    public $Name;

    /**
     * @var null
     */
    public $Description;

    /**
     * @var string|null
     */
    public $Variance;

    public function __construct(
        string $name,
        ?string $type = null,
        ?string $variance = null,
        ?string $class = null,
        ?string $member = null,
        bool $legacyNullable = false
    ) {
        parent::__construct('template', $name, $type, null, $class, $member, $legacyNullable);
        $this->Variance = $variance;
        if (!$this->Name) {
            throw new UnexpectedValueException(sprintf('Invalid name: %s', $name));
        }
    }

    public function __toString(): string
    {
        return sprintf(
            '@template%s %s%s',
            $this->Variance ? '-' . $this->Variance : '',
            $this->Name,
            $this->Type ? ' of ' . $this->Type : ''
        );
    }
}
