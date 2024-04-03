<?php declare(strict_types=1);

namespace Salient\Curler\Exception;

use Psr\Http\Message\RequestInterface;
use Salient\Contract\Http\HttpResponseInterface;
use Salient\Core\Utility\Arr;
use Throwable;

class HttpErrorException extends AbstractCurlerException
{
    public function __construct(
        RequestInterface $request,
        HttpResponseInterface $response,
        ?Throwable $previous = null
    ) {
        parent::__construct(
            sprintf('HTTP error %s', Arr::implode(' ', [
                (string) $response->getStatusCode(),
                $response->getReasonPhrase(),
            ])),
            $request,
            $response,
            $previous,
        );
    }

    public function getRequest(): RequestInterface
    {
        return $this->Request;
    }

    public function getResponse(): HttpResponseInterface
    {
        return $this->Response;
    }
}
