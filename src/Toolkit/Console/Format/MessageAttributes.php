<?php declare(strict_types=1);

namespace Salient\Console\Format;

use Salient\Contract\Console\Format\MessageAttributesInterface;
use Salient\Core\Concern\ImmutableTrait;

/**
 * @internal
 */
final class MessageAttributes implements MessageAttributesInterface
{
    use ImmutableTrait;

    /** @var self::LEVEL_* */
    private int $Level;
    /** @var self::TYPE_* */
    private int $Type;
    private bool $IsMsg1 = false;
    private bool $IsMsg2 = false;
    private bool $IsPrefix = false;

    /**
     * @param MessageAttributes::LEVEL_* $level
     * @param MessageAttributes::TYPE_* $type
     */
    public function __construct(int $level, int $type)
    {
        $this->Level = $level;
        $this->Type = $type;
    }

    /**
     * @inheritDoc
     */
    public function getLevel(): int
    {
        return $this->Level;
    }

    /**
     * @inheritDoc
     */
    public function getType(): int
    {
        return $this->Type;
    }

    /**
     * @inheritDoc
     */
    public function isMsg1(): bool
    {
        return $this->IsMsg1;
    }

    /**
     * @inheritDoc
     */
    public function isMsg2(): bool
    {
        return $this->IsMsg2;
    }

    /**
     * @inheritDoc
     */
    public function isPrefix(): bool
    {
        return $this->IsPrefix;
    }

    /**
     * @inheritDoc
     */
    public function withMsg1()
    {
        return $this->with('IsMsg1', true);
    }

    /**
     * @inheritDoc
     */
    public function withMsg2()
    {
        return $this->with('IsMsg2', true);
    }

    /**
     * @inheritDoc
     */
    public function withPrefix()
    {
        return $this->with('IsPrefix', true);
    }
}
