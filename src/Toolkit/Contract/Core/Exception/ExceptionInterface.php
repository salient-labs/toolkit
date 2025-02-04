<?php declare(strict_types=1);

namespace Salient\Contract\Core\Exception;

use Stringable;
use Throwable;

/**
 * @api
 */
interface ExceptionInterface extends Throwable
{
    /**
     * Get exit status to return if the exception is not caught
     */
    public function getExitStatus(): ?int;

    /**
     * Get exception metadata
     *
     * @return array<string,int|float|string|bool|Stringable|null>
     */
    public function getMetadata(): array;
}
