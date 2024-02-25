<?php declare(strict_types=1);

namespace Salient\Core;

final class Indentation
{
    /**
     * @readonly
     */
    public bool $InsertSpaces;

    /**
     * @readonly
     */
    public int $TabSize;

    public function __construct(bool $insertSpaces, int $tabSize)
    {
        $this->InsertSpaces = $insertSpaces;
        $this->TabSize = $tabSize;
    }
}
