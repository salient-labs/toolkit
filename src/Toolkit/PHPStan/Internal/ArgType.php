<?php declare(strict_types=1);

namespace Salient\PHPStan\Internal;

use PHPStan\Type\Type;

/**
 * @internal
 *
 * @mixin Type
 */
final class ArgType
{
    public Type $Type;
    public bool $IsOptional;
    public bool $IsFromUnpackedConstantArray;

    public function __construct(
        Type $type,
        bool $isOptional = false,
        bool $isFromUnpackedConstantArray = false
    ) {
        $this->Type = $type;
        $this->IsOptional = $isOptional;
        $this->IsFromUnpackedConstantArray = $isFromUnpackedConstantArray;
    }

    /**
     * @param mixed[] $arguments
     * @return mixed
     */
    public function __call(string $name, array $arguments)
    {
        return $this->Type->{$name}(...$arguments);
    }
}
