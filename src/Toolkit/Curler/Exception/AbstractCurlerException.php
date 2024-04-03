<?php declare(strict_types=1);

namespace Salient\Curler\Exception;

use Psr\Http\Message\RequestInterface;
use Salient\Contract\Http\HttpResponseInterface;
use Salient\Core\AbstractException;
use Throwable;

abstract class AbstractCurlerException extends AbstractException
{
    protected RequestInterface $Request;
    protected HttpResponseInterface $Response;

    public function __construct(
        string $message = '',
        ?RequestInterface $request = null,
        ?HttpResponseInterface $response = null,
        ?Throwable $previous = null
    ) {
        if ($request !== null) {
            $this->Request = $request;
        }
        if ($response !== null) {
            $this->Response = $response;
        }

        parent::__construct($message, $previous);
    }

    public function getRequest(): ?RequestInterface
    {
        return $this->Request ?? null;
    }

    public function getResponse(): ?HttpResponseInterface
    {
        return $this->Response ?? null;
    }
}
