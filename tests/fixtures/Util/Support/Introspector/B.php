<?php declare(strict_types=1);

namespace Lkrms\Tests\Support\Introspector;

use Lkrms\Concern\TConstructible;
use Salient\Core\Contract\IConstructible;
use DateTimeImmutable;

class B extends A implements IConstructible
{
    use TConstructible;

    /**
     * @var mixed[]
     */
    private array $Data = [];

    /**
     * @var mixed
     */
    private $Meta;

    protected DateTimeImmutable $CreatedAt;

    protected DateTimeImmutable $ModifiedAt;

    // @phpstan-ignore-next-line
    private string $LastSetter;

    public function __construct(DateTimeImmutable $createdAt)
    {
        $this->CreatedAt = $createdAt;
        $this->ModifiedAt = $createdAt;
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
}
