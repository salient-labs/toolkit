<?php declare(strict_types=1);

namespace Salient\Curler\Exception;

use Psr\Http\Message\RequestInterface as PsrRequestInterface;
use Salient\Core\Exception\Exception;
use Salient\Http\Message\Request;
use Salient\Utility\Format;
use Throwable;

/**
 * @api
 */
class GenericRequestException extends Exception
{
    protected PsrRequestInterface $Request;
    /** @var array<string,mixed> */
    protected array $Data;

    /**
     * @param array<string,mixed> $data
     */
    public function __construct(
        string $message,
        PsrRequestInterface $request,
        array $data = [],
        ?Throwable $previous = null
    ) {
        $this->Request = $request;
        $this->Data = $data;

        parent::__construct($message, $previous);
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
    public function getMetadata(): array
    {
        return [
            'Request' => (string) Request::fromPsr7($this->Request),
            'Data' => $this->Data
                ? Format::array($this->Data)
                : '<none>',
        ];
    }
}
