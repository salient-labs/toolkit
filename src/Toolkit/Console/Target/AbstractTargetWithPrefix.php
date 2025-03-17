<?php declare(strict_types=1);

namespace Salient\Console\Target;

use Salient\Contract\Console\Format\FormatterInterface as Formatter;
use Salient\Contract\Console\Target\HasPrefix;

/**
 * Base class for console output targets that apply an optional prefix to each
 * line of output
 */
abstract class AbstractTargetWithPrefix extends AbstractTarget implements HasPrefix
{
    private ?string $Prefix = null;
    private int $PrefixLength = 0;

    /**
     * @param self::LEVEL_* $level
     * @param array<string,mixed> $context
     */
    abstract protected function writeToTarget(int $level, string $message, array $context): void;

    /**
     * @inheritDoc
     */
    final public function write(int $level, string $message, array $context = []): void
    {
        $this->assertIsValid();

        if ($this->Prefix === null) {
            $this->writeToTarget($level, $message, $context);
            return;
        }

        $this->writeToTarget(
            $level,
            $this->Prefix . str_replace("\n", "\n{$this->Prefix}", $message),
            $context
        );
    }

    /**
     * @inheritDoc
     */
    final public function setPrefix(?string $prefix)
    {
        if ($prefix === null || $prefix === '') {
            $this->Prefix = null;
            $this->PrefixLength = 0;

            return $this;
        }

        $this->assertIsValid();

        $this->PrefixLength = strlen($prefix);
        $this->Prefix = $this->getFormatter()->getTagFormat(Formatter::TAG_LOW_PRIORITY)->apply($prefix);

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

        return 80 - $this->PrefixLength;
    }
}
