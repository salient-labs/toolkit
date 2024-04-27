<?php declare(strict_types=1);

namespace Salient\Curler\Exception;

use Psr\Http\Message\RequestInterface;
use Salient\Contract\Http\HttpResponseInterface;
use Salient\Core\Utility\Format;
use Salient\Core\AbstractException;
use Throwable;

abstract class AbstractCurlerException extends AbstractException
{
    protected RequestInterface $Request;
    protected HttpResponseInterface $Response;
    /** @var array<string,mixed> */
    protected array $Data;

    /**
     * @param array<string,mixed> $data
     */
    public function __construct(
        string $message = '',
        ?RequestInterface $request = null,
        ?HttpResponseInterface $response = null,
        array $data = [],
        ?Throwable $previous = null
    ) {
        if ($request !== null) {
            $this->Request = $request;
        }
        if ($response !== null) {
            $this->Response = $response;
        }
        $this->Data = $data;

        parent::__construct($message, $previous);
    }

    /**
     * Get the request that triggered the exception, if applicable
     */
    public function getRequest(): ?RequestInterface
    {
        return $this->Request ?? null;
    }

    /**
     * Get the response that triggered the exception, if applicable
     */
    public function getResponse(): ?HttpResponseInterface
    {
        return $this->Response ?? null;
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
            'Response' =>
                (string) ($this->Response ?? '<no response>'),
            'Request' =>
                (string) ($this->Response ?? '<no request>'),
            'Data' =>
                $this->Data
                    ? Format::array($this->Data)
                    : '<no data>',
        ];
    }
}
