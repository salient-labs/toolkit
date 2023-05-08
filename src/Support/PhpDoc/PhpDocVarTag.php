<?php declare(strict_types=1);

namespace Lkrms\Support\PhpDoc;

use UnexpectedValueException;

/**
 * A "var" tag extracted from a PHP DocBlock
 *
 */
class PhpDocVarTag extends PhpDocTag
{
    /**
     * @var string
     */
    public $Type;

    public function __construct(string $type, ?string $name = null, ?string $description = null, bool $legacyNullable = false)
    {
        parent::__construct('var', $name, $type, $description, $legacyNullable);
        if (!$this->Type) {
            throw new UnexpectedValueException(sprintf('Invalid type: %s', $type));
        }
    }
}
