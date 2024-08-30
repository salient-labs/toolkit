<?php declare(strict_types=1);

namespace Salient\Core\Concern;

use Salient\Contract\Core\ExceptionInterface;
use Throwable;

/**
 * @phpstan-require-implements ExceptionInterface
 */
trait ExceptionTrait
{
    protected ?int $ExitStatus;

    public function __construct(
        string $message = '',
        ?Throwable $previous = null,
        ?int $exitStatus = null
    ) {
        $this->ExitStatus = $exitStatus;

        parent::__construct($message, 0, $previous);
    }

    /**
     * @param mixed ...$args
     */
    public static function withExitStatus(?int $exitStatus, ...$args): ExceptionInterface
    {
        // @phpstan-ignore-next-line
        $instance = new static(...$args);
        $instance->ExitStatus = $exitStatus;
        return $instance;
    }

    /**
     * @inheritDoc
     */
    public function getExitStatus(): ?int
    {
        return $this->ExitStatus;
    }

    /**
     * @codeCoverageIgnore
     */
    public function getMetadata(): array
    {
        return [];
    }

    /**
     * @inheritDoc
     */
    public function __toString(): string
    {
        $detail = '';
        foreach ($this->getMetadata() as $key => $value) {
            $detail .= sprintf("\n\n%s:\n%s", $key, rtrim((string) $value, "\n"));
        }

        return parent::__toString() . $detail;
    }
}
