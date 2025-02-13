<?php declare(strict_types=1);

namespace Salient\Utility\Support;

/**
 * @api
 */
final class Indentation
{
    /** @readonly */
    public bool $InsertSpaces;

    /**
     * @readonly
     * @var int<1,max>
     */
    public int $TabSize;

    /**
     * @param int<1,max> $tabSize
     */
    public function __construct(bool $insertSpaces, int $tabSize)
    {
        $this->InsertSpaces = $insertSpaces;
        $this->TabSize = $tabSize;
    }
}
