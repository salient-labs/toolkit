<?php declare(strict_types=1);

namespace Salient\PHPDoc;

use Salient\Core\Exception\InvalidArgumentException;

/**
 * A "param" tag extracted from a PHP DocBlock
 */
class PHPDocParamTag extends PHPDocTag
{
    /** @var string */
    public $Name;
    /** @var bool */
    public $IsVariadic;

    public function __construct(
        string $name,
        ?string $type = null,
        bool $isVariadic = false,
        ?string $description = null,
        ?string $class = null,
        ?string $member = null,
        bool $legacyNullable = false
    ) {
        parent::__construct('param', $name, $type, $description, $class, $member, $legacyNullable);
        $this->IsVariadic = $isVariadic;
        if (!$this->Name) {
            throw new InvalidArgumentException(sprintf('Invalid name: %s', $name));
        }
    }
}
