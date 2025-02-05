<?php declare(strict_types=1);

namespace Salient\Tests\Core\Introspector;

use Salient\Contract\Core\Entity\Temporal;

class C implements Temporal
{
    public const FLAG = 1;
    public const BOOLEAN = 0;

    public string $Long;
    public ?string $Short;
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
        ?string &$description = null
    ) {
        $this->Long = $long;
        $this->Short = $short;
        $this->ValueName = $valueName;
        $this->Type = $type;
        $this->ValueType = $valueType;
        $this->Description = &$description;
    }

    /**
     * @inheritDoc
     */
    public static function getDateProperties(): array
    {
        return ['*'];
    }
}
