<?php declare(strict_types=1);

namespace Salient\Tests\Core\Introspector;

use Salient\Contract\Core\Entity\Constructible;
use Salient\Core\Concern\ConstructibleTrait;
use Salient\Utility\Date;
use DateTimeImmutable;
use DateTimeInterface;

class B extends A implements Constructible
{
    use ConstructibleTrait;

    /** @var mixed[] */
    private array $Data = [];
    /** @var mixed */
    private $Meta;
    protected DateTimeInterface $CreatedAt;
    protected DateTimeImmutable $ModifiedAt;
    // @phpstan-ignore property.onlyWritten
    private string $LastSetter;
    // @phpstan-ignore property.onlyWritten
    private string $Secret;

    public function __construct(DateTimeInterface $createdAt)
    {
        $this->CreatedAt = $createdAt;
        $this->ModifiedAt = Date::immutable($createdAt);
        $this->LastSetter = __METHOD__;
    }

    /**
     * @return mixed[]
     */
    protected function _getData(): array
    {
        return $this->Data;
    }

    /**
     * @param mixed[] $value
     */
    protected function _setData(array $value): void
    {
        $this->Data = $value;
        $this->ModifiedAt = new DateTimeImmutable();
        $this->LastSetter = __METHOD__;
    }

    /**
     * @return mixed
     */
    protected function _getMeta()
    {
        return $this->Meta;
    }

    protected function _issetMeta(): bool
    {
        return isset($this->Meta);
    }

    /**
     * @param mixed $value
     */
    protected function _setMeta($value): void
    {
        $this->Meta = $value;
        $this->ModifiedAt = new DateTimeImmutable();
        $this->LastSetter = __METHOD__;
    }

    protected function _unsetMeta(): void
    {
        $this->Meta = null;
        $this->ModifiedAt = new DateTimeImmutable();
        $this->LastSetter = __METHOD__;
    }

    protected function _setSecret(string $value): void
    {
        $this->Secret = $value;
    }
}
