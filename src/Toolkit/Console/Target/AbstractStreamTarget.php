<?php declare(strict_types=1);

namespace Salient\Console\Target;

use Salient\Console\Format\TtyFormat;
use Salient\Contract\Console\Format\FormatterInterface;
use Salient\Contract\Console\Target\StreamTargetInterface;
use Salient\Contract\HasEscapeSequence;

/**
 * @api
 */
abstract class AbstractStreamTarget extends AbstractTargetWithPrefix implements
    StreamTargetInterface,
    HasEscapeSequence
{
    /**
     * @inheritDoc
     */
    public function isStdout(): bool
    {
        return false;
    }

    /**
     * @inheritDoc
     */
    public function isStderr(): bool
    {
        return false;
    }

    /**
     * @inheritDoc
     */
    public function isTty(): bool
    {
        return false;
    }

    /**
     * @inheritDoc
     */
    protected function createFormatter(): FormatterInterface
    {
        return $this->isTty()
            ? TtyFormat::getFormatter($this)
            : parent::createFormatter();
    }
}
