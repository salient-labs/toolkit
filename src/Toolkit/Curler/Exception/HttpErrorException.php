<?php declare(strict_types=1);

namespace Salient\Curler\Exception;

use Psr\Http\Message\RequestInterface;
use Salient\Contract\Curler\Exception\HttpErrorExceptionInterface;
use Salient\Contract\Http\HttpResponseInterface;
use Salient\Utility\Arr;
use Throwable;

/**
 * @internal
 */
class HttpErrorException extends AbstractResponseException implements HttpErrorExceptionInterface
{
    protected int $StatusCode;

    /**
     * @param array<string,mixed> $data
     */
    public function __construct(
        RequestInterface $request,
        HttpResponseInterface $response,
        array $data = [],
        ?Throwable $previous = null
    ) {
        $this->StatusCode = $response->getStatusCode();

        parent::__construct(
            sprintf('HTTP error %s', Arr::implode(' ', [
                (string) $this->StatusCode,
                $response->getReasonPhrase(),
            ])),
            $request,
            $response,
            $data,
            $previous,
        );
    }

    /**
     * @inheritDoc
     */
    public function getStatusCode(): int
    {
        return $this->StatusCode;
    }

    /**
     * @inheritDoc
     */
    public function isNotFoundError(): bool
    {
        return $this->StatusCode === 404
            || $this->StatusCode === 410;
    }
}
