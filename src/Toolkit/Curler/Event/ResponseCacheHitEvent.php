<?php declare(strict_types=1);

namespace Salient\Curler\Event;

use Psr\Http\Message\RequestInterface as PsrRequestInterface;
use Salient\Contract\Curler\Event\ResponseCacheHitEvent as ResponseCacheHitEventInterface;
use Salient\Contract\Curler\CurlerInterface;
use Salient\Contract\Http\Message\ResponseInterface;

/**
 * @internal
 */
class ResponseCacheHitEvent extends CurlerEvent implements ResponseCacheHitEventInterface
{
    protected PsrRequestInterface $Request;
    protected ResponseInterface $Response;

    public function __construct(CurlerInterface $curler, PsrRequestInterface $request, ResponseInterface $response)
    {
        $this->Request = $request;
        $this->Response = $response;

        parent::__construct($curler);
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
