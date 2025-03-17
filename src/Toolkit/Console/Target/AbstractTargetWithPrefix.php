<?php declare(strict_types=1);

namespace Salient\Console\Target;

use Salient\Contract\Console\Format\FormatterInterface as Formatter;
use Salient\Contract\Console\Target\HasPrefix;

/**
 * @api
 */
abstract class AbstractTargetWithPrefix extends AbstractTarget implements HasPrefix
{
    private ?string $Prefix = null;
    private int $PrefixWidth = 0;

    /**
     * @param self::LEVEL_* $level
     * @param array<string,mixed> $context
     */
    abstract protected function doWrite(int $level, string $message, array $context): void;

    /**
     * @inheritDoc
     */
    final public function write(int $level, string $message, array $context = []): void
    {
        $this->assertIsValid();

        if ($this->Prefix !== null) {
            $message = $this->Prefix . str_replace("\n", "\n" . $this->Prefix, $message);
        }

        $this->doWrite($level, $message, $context);
    }

    /**
     * @inheritDoc
     */
    final public function setPrefix(?string $prefix)
    {
        $this->assertIsValid();

        if ($prefix === null || $prefix === '') {
            $this->Prefix = null;
            $this->PrefixWidth = 0;
        } else {
            $formatted = $this
                ->getFormatter()
                ->getTagFormat(Formatter::TAG_LOW_PRIORITY)
                ->apply($prefix);
            $this->PrefixWidth = mb_strlen($prefix);
            $this->Prefix = $formatted;
        }
        return $this;
    }

    /**
     * @inheritDoc
     */
    final public function getPrefix(): ?string
    {
        $this->assertIsValid();

        return $this->Prefix;
    }

    /**
     * @inheritDoc
     */
    public function getWidth(): ?int
    {
        $this->assertIsValid();

        return 80 - $this->PrefixWidth;
    }
}
