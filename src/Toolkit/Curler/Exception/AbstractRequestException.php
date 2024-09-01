<?php declare(strict_types=1);

namespace Salient\Curler\Exception;

use Psr\Http\Message\RequestInterface;
use Salient\Core\AbstractException;
use Salient\Http\HttpRequest;
use Salient\Utility\Format;
use Throwable;

/**
 * @internal
 */
abstract class AbstractRequestException extends AbstractException
{
    protected RequestInterface $Request;
    /** @var array<string,mixed> */
    protected array $Data;

    /**
     * @param array<string,mixed> $data
     */
    public function __construct(
        string $message,
        RequestInterface $request,
        array $data = [],
        ?Throwable $previous = null
    ) {
        $this->Request = $request;
        $this->Data = $data;

        parent::__construct($message, $previous);
    }

    /**
     * Get the request that triggered the exception
     */
    public function getRequest(): RequestInterface
    {
        return $this->Request;
    }

    /**
     * Get data provided by the handler that raised the exception
     *
     * @return array<string,mixed>
     */
    public function getData(): array
    {
        return $this->Data;
    }

    /**
     * @inheritDoc
     */
    public function getMetadata(): array
    {
        return [
            'Request' =>
                (string) HttpRequest::fromPsr7($this->Request),
            'Data' =>
                $this->Data
                    ? Format::array($this->Data)
                    : '<no data>',
        ];
    }
}
