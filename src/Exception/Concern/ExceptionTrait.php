<?php declare(strict_types=1);

namespace Lkrms\Exception\Concern;

use Lkrms\Exception\Contract\ExceptionInterface;
use Throwable;

/**
 * Implements ExceptionInterface
 *
 * @see ExceptionInterface
 */
trait ExceptionTrait
{
    protected ?int $ExitStatus = null;

    public function __construct(
        string $message = '',
        ?Throwable $previous = null,
        ?int $exitStatus = null
    ) {
        $this->ExitStatus = $exitStatus;
        parent::__construct($message, 0, $previous);
    }

    /**
     * Set the exit status to return if the exception is not caught on the
     * command line
     *
     * @return static
     */
    public function withExitStatus(?int $exitStatus)
    {
        $clone = clone $this;
        $clone->ExitStatus = $exitStatus;
        return $clone;
    }

    /**
     * Get an array that maps names to formatted content
     *
     * @return array<string,string>
     */
    public function getDetail(): array
    {
        return [];
    }

    /**
     * @inheritDoc
     */
    public function getExitStatus(): ?int
    {
        return $this->ExitStatus;
    }

    /**
     * @inheritDoc
     */
    public function __toString(): string
    {
        $detail = '';
        foreach ($this->getDetail() as $key => $value) {
            $detail .= sprintf("\n\n%s:\n%s", $key, $value);
        }

        return parent::__toString() . $detail;
    }
}
