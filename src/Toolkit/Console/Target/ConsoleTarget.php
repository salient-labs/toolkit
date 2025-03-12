<?php declare(strict_types=1);

namespace Salient\Console\Target;

use Salient\Console\Format\ConsoleFormatter as Formatter;
use Salient\Console\Format\ConsoleMessageFormats as MessageFormats;
use Salient\Console\Format\ConsoleTagFormats as TagFormats;
use Salient\Contract\Console\Target\ConsoleTargetInterface;

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
