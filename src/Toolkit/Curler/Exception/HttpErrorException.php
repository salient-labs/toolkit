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
    /**
     * @param array<string,mixed> $data
     */
    public function __construct(
        RequestInterface $request,
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
