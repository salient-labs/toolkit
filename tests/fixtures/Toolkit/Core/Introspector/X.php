<?php declare(strict_types=1);

namespace Salient\Tests\Core\Introspector;

use Salient\Contract\Core\Entity\Normalisable;
use Salient\Contract\Core\Entity\Readable;
use Salient\Contract\Core\Entity\Relatable;
use Salient\Contract\Core\Entity\Writable;
use Salient\Core\Concern\ReadableTrait;
use Salient\Core\Concern\WritableTrait;
use Salient\Utility\Str;

class X implements Readable, Writable, Normalisable, Relatable
{
    use ReadableTrait;
    use WritableTrait;

    protected int $MyInt;
    protected Y $MyY;

    public static function getReadableProperties(): array
    {
        return ['MyInt', 'MyY'];
    }

    public static function getRelationships(): array
    {
        return ['MyY' => [self::ONE_TO_ONE => Y::class]];
    }

    public static function normaliseProperty(
        string $name,
        bool $fromData = true,
        string ...$declaredName
    ): string {
        return Str::kebab($name);
    }

    protected function _setMyY(Y $myY): void
    {
        $this->MyY = $myY;
    }

    protected function _unsetMyY(): void
    {
        unset($this->MyY);
    }
}
