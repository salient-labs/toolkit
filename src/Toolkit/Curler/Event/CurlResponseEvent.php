<?php declare(strict_types=1);

namespace Salient\Curler\Event;

use Psr\Http\Message\RequestInterface as PsrRequestInterface;
use Salient\Contract\Curler\Event\CurlResponseEvent as CurlResponseEventInterface;
use Salient\Contract\Curler\CurlerInterface;
use Salient\Contract\Http\Message\ResponseInterface;
use CurlHandle;

/**
 * @internal
 */
class CurlResponseEvent extends CurlEvent implements CurlResponseEventInterface
{
    protected PsrRequestInterface $Request;
    protected ResponseInterface $Response;

    /**
     * @param CurlHandle|resource $curlHandle
     */
    public function __construct(CurlerInterface $curler, $curlHandle, PsrRequestInterface $request, ResponseInterface $response)
    {
        $this->Request = $request;
        $this->Response = $response;

        parent::__construct($curler, $curlHandle);
    }

    /**
     * @inheritDoc
     */
    public function getRequest(): PsrRequestInterface
    {
        return $this->Request;
    }

    /**
     * @inheritDoc
     */
    public function getResponse(): ResponseInterface
    {
        return $this->Response;
    }
}
