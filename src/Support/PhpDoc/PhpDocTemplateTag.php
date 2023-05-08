<?php declare(strict_types=1);

namespace Lkrms\Support\PhpDoc;

use UnexpectedValueException;

/**
 * A "template" tag extracted from a PHP DocBlock
 *
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
     * @var bool
     */
    public $IsClassTemplate;

    public function __construct(string $name, ?string $type = null, bool $legacyNullable = false)
    {
        parent::__construct('template', $name, $type, null, $legacyNullable);
        if (!$this->Name) {
            throw new UnexpectedValueException(sprintf('Invalid name: %s', $name));
        }
    }
}
