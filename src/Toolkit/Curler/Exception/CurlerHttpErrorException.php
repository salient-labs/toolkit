<?php declare(strict_types=1);

namespace Salient\Curler\Exception;

use Salient\Curler\Curler;

/**
 * Thrown when an HTTP response is received with a status code of 400 or above
 */
class CurlerHttpErrorException extends CurlerException
{
    protected int $StatusCode;
    protected ?string $ReasonPhrase;

    /**
     * Creates a new CurlerHttpErrorException object
     */
    public function __construct(
        int $statusCode,
        ?string $reasonPhrase,
        Curler $curler
    ) {
        $this->StatusCode = $statusCode;
        $this->ReasonPhrase = $reasonPhrase;

        parent::__construct(
            sprintf('HTTP error %d %s', $statusCode, $reasonPhrase),
            $curler,
        );
    }

    /**
     * Get the HTTP status code returned by the server
     */
    public function getStatusCode(): int
    {
        return $this->StatusCode;
    }

    /**
     * Get the reason phrase returned by the server, if any
     */
    public function getReasonPhrase(): ?string
    {
        return $this->ReasonPhrase;
    }

    /**
     * True if the HTTP status code indicates the requested resource does not
     * exist
     *
     * Returns `true` if the status code is 404 (Not Found) or 410 (Gone). Also
     * returns `true` if the status code is 400 (Bad Request) if `$orBadRequest`
     * is `true`.
     */
    public function isNotFound(bool $orBadRequest = false): bool
    {
        return
            $this->StatusCode === 404 ||  // Not Found
            $this->StatusCode === 410 ||  // Gone
            ($orBadRequest &&
                $this->StatusCode === 400);  // Bad Request
    }
}
