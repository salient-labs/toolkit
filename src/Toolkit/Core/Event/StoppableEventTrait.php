<?php declare(strict_types=1);

namespace Salient\Core\Event;

use Salient\Contract\Core\Event\StoppableEventInterface;

/**
 * @api
 *
 * @phpstan-require-implements StoppableEventInterface
 */
trait StoppableEventTrait
{
    private bool $Propagate = true;

    /**
     * @inheritDoc
     */
    public function isPropagationStopped(): bool
    {
        return !$this->Propagate;
    }

    /**
     * @inheritDoc
     */
    public function stopPropagation()
    {
        $this->Propagate = false;
        return $this;
    }
}
