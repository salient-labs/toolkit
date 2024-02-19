<?php declare(strict_types=1);

namespace Salient\Core\Contract;

use Throwable;

/**
 * @api
 */
interface ExceptionInterface extends Throwable
{
    /**
     * Get an array that maps names to formatted content
     *
     * @return array<string,string>
     */
    public function getDetail(): array;

    /**
     * Get the exit status to return if the exception is not caught on the
     * command line
     */
    public function getExitStatus(): ?int;
}
