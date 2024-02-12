<?php declare(strict_types=1);

namespace Lkrms\Tests\Support\Introspector;

class C
{
    public const FLAG = 0;
    public const BOOLEAN = 0;

    protected string $Long;
    protected ?string $Short;
    protected ?string $ValueName;
    protected int $Type;
    protected int $ValueType;
    protected ?string $Description;

    public function __construct(
        string $long,
        ?string $short,
        ?string $valueName,
        int $type = self::FLAG,
        int $valueType = self::BOOLEAN,
        ?string $description = null
    ) {
        $this->Long = $long;
        $this->Short = $short;
        $this->ValueName = $valueName;
        $this->Type = $type;
        $this->ValueType = $valueType;
        $this->Description = $description;
    }
}
