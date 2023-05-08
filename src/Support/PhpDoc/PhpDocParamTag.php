<?php declare(strict_types=1);

namespace Lkrms\Support\PhpDoc;

use UnexpectedValueException;

/**
 * A "param" tag extracted from a PHP DocBlock
 *
 */
class PhpDocParamTag extends PhpDocTag
{
    /**
     * @var string
     */
    public $Name;

    public function __construct(string $name, ?string $type = null, ?string $description = null, bool $legacyNullable = true)
    {
        parent::__construct('param', $name, $type, $description, $legacyNullable);
        if (!$this->Name) {
            throw new UnexpectedValueException(sprintf('Invalid name: %s', $name));
        }
    }
}
