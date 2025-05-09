<?php declare(strict_types=1);

namespace Salient\Http\Exception;

use Salient\Contract\Http\Exception\InvalidHeaderException as InvalidHeaderExceptionInterface;
use Throwable;

/**
 * @internal
 */
class InvalidHeaderException extends HttpException implements InvalidHeaderExceptionInterface
{
    protected ?int $StatusCode;

    public function __construct(
        string $message = '',
        ?Throwable $previous = null,
        ?int $statusCode = null,
        ?int $exitStatus = null
    ) {
        $this->StatusCode = $statusCode;

        parent::__construct($message, $previous, $exitStatus);
    }

    /**
     * @inheritDoc
     */
    public function getStatusCode(): ?int
    {
        return $this->StatusCode;
    }
}
