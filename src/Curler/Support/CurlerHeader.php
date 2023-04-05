<?php declare(strict_types=1);

namespace Lkrms\Curler\Support;

use Lkrms\Concern\TFullyReadable;
use Lkrms\Contract\IImmutable;
use Lkrms\Contract\IReadable;

/**
 * An immutable HTTP header
 *
 * @property-read string $Name
 * @property-read string $Value
 * @property-read int $Index
 */
final class CurlerHeader implements IReadable, IImmutable
{
    use TFullyReadable;

    /**
     * @var string
     */
    protected $Name;

    /**
     * @var string
     */
    protected $Value;

    /**
     * @var int
     */
    protected $Index;

    public function __construct(string $name, string $value, int $index)
    {
        $this->Name = $name;
        $this->Value = $value;
        $this->Index = $index;
    }

    /**
     * @return $this
     */
    public function withValueExtended(string $value)
    {
        $clone = clone $this;
        $clone->Value .= $value;

        return $clone;
    }

    public function getHeader(): string
    {
        return "{$this->Name}:{$this->Value}";
    }
}
