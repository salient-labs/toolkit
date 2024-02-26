<?php declare(strict_types=1);

namespace Salient\PhpDoc;

use Salient\Core\Exception\InvalidArgumentException;

/**
 * A "return" tag extracted from a PHP DocBlock
 */
class PhpDocReturnTag extends PhpDocTag
{
    /**
     * @var string
     */
    public $Type;

    /**
     * @var null
     */
    public $Name;

    public function __construct(
        string $type,
        ?string $description = null,
        ?string $class = null,
        ?string $member = null,
        bool $legacyNullable = false
    ) {
        parent::__construct('return', null, $type, $description, $class, $member, $legacyNullable);
        if (!$this->Type) {
            throw new InvalidArgumentException(sprintf('Invalid type: %s', $type));
        }
    }
}
