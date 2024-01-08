<?php declare(strict_types=1);

namespace Lkrms\Console\Concept;

use Lkrms\Console\Contract\ConsoleTargetInterface;
use Lkrms\Console\Support\ConsoleMessageFormats as MessageFormats;
use Lkrms\Console\Support\ConsoleTagFormats as TagFormats;
use Lkrms\Console\ConsoleFormatter as Formatter;

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
        return null;
    }

    protected function createTagFormats(): TagFormats
    {
        return new TagFormats();
    }

    protected function createMessageFormats(): MessageFormats
    {
        return new MessageFormats();
    }
}
