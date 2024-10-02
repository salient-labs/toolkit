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
     * Get an instance with an exit status to return if the exception is not
     * caught on the command line
     *
     * @return static
     */
    public static function withExitStatus(?int $exitStatus): ExceptionInterface;

    /**
     * Get the exit status to return if the exception is not caught on the
     * command line
     */
    public function getExitStatus(): ?int;

    /**
     * Get exception metadata as an associative array
     *
     * @return array<string,int|float|string|bool|Stringable|null>
     */
    public function getMetadata(): array;
}
