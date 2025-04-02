<?php declare(strict_types=1);

namespace Salient\Curler\Exception;

use Psr\Http\Message\RequestInterface;
use Salient\Contract\Http\Message\HttpResponseInterface;
use Throwable;

/**
 * @api
 */
class GenericResponseException extends GenericRequestException
{
    protected HttpResponseInterface $Response;

    /**
     * @param array<string,mixed> $data
     */
    public function __construct(
        string $message,
        RequestInterface $request,
        HttpResponseInterface $response,
        array $data = [],
        ?Throwable $previous = null
    ) {
        $this->Response = $response;

        parent::__construct($message, $request, $data, $previous);
    }

    /**
     * @inheritDoc
     */
    public function getResponse(): HttpResponseInterface
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
