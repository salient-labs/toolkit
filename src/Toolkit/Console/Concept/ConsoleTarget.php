<?php declare(strict_types=1);

namespace Salient\Console\Concept;

use Salient\Console\Contract\ConsoleTargetInterface;
use Salient\Console\Support\ConsoleMessageFormats as MessageFormats;
use Salient\Console\Support\ConsoleTagFormats as TagFormats;
use Salient\Console\ConsoleFormatter as Formatter;

/**
 * Base class for console output targets
 */
abstract class ConsoleTarget implements ConsoleTargetInterface
{
    private Formatter $Formatter;

    /**
     * @inheritDoc
     */
    public function getFormatter(): Formatter
    {
        $this->assertIsValid();

        return $this->Formatter ??= new Formatter(
            $this->createTagFormats(),
            $this->createMessageFormats(),
            fn(): ?int => $this->getWidth(),
        );
    }

    /**
     * @inheritDoc
     */
    public function getWidth(): ?int
    {
        $this->assertIsValid();

        return null;
    }

    /**
     * @inheritDoc
     */
    public function close(): void {}

    protected function assertIsValid(): void {}

    protected function createTagFormats(): TagFormats
    {
        return new TagFormats();
    }

    protected function createMessageFormats(): MessageFormats
    {
        return new MessageFormats();
    }
}
