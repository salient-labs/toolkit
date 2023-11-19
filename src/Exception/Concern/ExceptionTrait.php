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
    public function __construct(
        string $message = '',
        ?Throwable $previous = null
    ) {
        parent::__construct($message, 0, $previous);
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
    public function __toString(): string
    {
        $detail = '';
        foreach ($this->getDetail() as $key => $value) {
            $detail .= sprintf("\n\n%s:\n%s", $key, $value);
        }

        return parent::__toString() . $detail;
    }
}
