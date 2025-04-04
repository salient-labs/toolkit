<?php declare(strict_types=1);

namespace Salient\Curler\Exception;

use Psr\Http\Message\RequestInterface as PsrRequestInterface;
use Salient\Contract\Curler\Exception\HttpErrorException as HttpErrorExceptionInterface;
use Salient\Contract\Http\Message\HttpResponseInterface;
use Salient\Utility\Arr;
use Throwable;

/**
 * @internal
 */
class HttpErrorException extends GenericResponseException implements HttpErrorExceptionInterface
{
    /**
     * @param array<string,mixed> $data
     */
    public function __construct(
        PsrRequestInterface $request,
        HttpResponseInterface $response,
        array $data = [],
        ?Throwable $previous = null
    ) {
        parent::__construct(
            sprintf('HTTP error %s', Arr::implode(' ', [
                (string) $response->getStatusCode(),
                $response->getReasonPhrase(),
            ])),
            $request,
            $response,
            $data,
            $previous,
        );
    }
}
