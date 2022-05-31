<?php

declare(strict_types=1);

namespace Lkrms\Curler;

use Lkrms\Core\Contract\IGettable;
use Lkrms\Core\Mixin\TFullyGettable;

/**
 * @property-read string $Name
 * @property-read string $Value
 */
final class CurlerHeader implements IGettable
{
    use TFullyGettable;

    /**
     * @internal
     * @var string
     */
    protected $Name;

    /**
     * @internal
     * @var string
     */
    protected $Value;

    public function __construct(string $name, string $value)
    {
        $this->Name  = $name;
        $this->Value = $value;
    }

    public function extendValue(string $value)
    {
        $this->Value .= $value;
    }

    public function getHeader(): string
    {
        return "{$this->Name}:{$this->Value}";
    }
}
