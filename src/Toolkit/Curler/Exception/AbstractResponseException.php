<?php declare(strict_types=1);

namespace Salient\Curler\Exception;

use Psr\Http\Message\RequestInterface;
use Salient\Contract\Http\HttpResponseInterface;
use Throwable;

/**
 * @internal
 */
abstract class AbstractResponseException extends AbstractRequestException
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
     * Get the response that triggered the exception
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
            'Response' =>
                (string) $this->Response,
        ] + parent::getMetadata();
    }
}
