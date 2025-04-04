<?php declare(strict_types=1);

namespace Salient\Curler\Exception;

use Psr\Http\Message\RequestInterface as PsrRequestInterface;
use Salient\Contract\Http\Message\ResponseInterface;
use Throwable;

/**
 * @api
 */
class GenericResponseException extends GenericRequestException
{
    protected ResponseInterface $Response;

    /**
     * @param array<string,mixed> $data
     */
    public function __construct(
        string $message,
        PsrRequestInterface $request,
        ResponseInterface $response,
        array $data = [],
        ?Throwable $previous = null
    ) {
        $this->Response = $response;

        parent::__construct($message, $request, $data, $previous);
    }

    /**
     * @inheritDoc
     */
    public function getResponse(): ResponseInterface
    {
        return $this->Response;
    }

    /**
     * @inheritDoc
     */
    public function getMetadata(): array
    {
        return [
            'Response' => (string) $this->Response,
        ] + parent::getMetadata();
    }
}
