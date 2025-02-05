<?php declare(strict_types=1);

namespace Salient\Console\Concept;

use Salient\Contract\Catalog\MessageLevel as Level;
use Salient\Contract\Console\ConsoleTag as Tag;
use Salient\Contract\Console\ConsoleTargetPrefixInterface;

/**
 * Base class for console output targets that apply an optional prefix to each
 * line of output
 */
abstract class ConsolePrefixTarget extends ConsoleTarget implements ConsoleTargetPrefixInterface
{
    private ?string $Prefix = null;
    private int $PrefixLength = 0;

    /**
     * @param Level::* $level
     * @param array<string,mixed> $context
     */
    abstract protected function writeToTarget($level, string $message, array $context): void;

    /**
     * @inheritDoc
     */
    final public function write($level, string $message, array $context = []): void
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
        $this->Prefix = $this->getFormatter()->getTagFormat(Tag::LOW_PRIORITY)->apply($prefix);

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
