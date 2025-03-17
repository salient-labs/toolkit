<?php declare(strict_types=1);

namespace Salient\Console\Target;

use Salient\Console\Format\Formatter;
use Salient\Contract\Console\Format\FormatterInterface;
use Salient\Contract\Console\Target\TargetInterface;

/**
 * @api
 */
abstract class AbstractTarget implements TargetInterface
{
    private FormatterInterface $Formatter;

    /**
     * @inheritDoc
     */
    final public function getFormatter(): FormatterInterface
    {
        $this->assertIsValid();

        return $this->Formatter ??= $this->createFormatter();
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

    /**
     * Throw an exception if the target is closed
     */
    protected function assertIsValid(): void {}

    /**
     * Create an output formatter for the target
     */
    protected function createFormatter(): FormatterInterface
    {
        return new Formatter(null, null, fn() => $this->getWidth());
    }
}
