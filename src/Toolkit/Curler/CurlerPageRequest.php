<?php declare(strict_types=1);

namespace Salient\Curler;

use Psr\Http\Message\RequestInterface;
use Salient\Contract\Curler\CurlerPageRequestInterface;

/**
 * A request for a page of data from an HTTP endpoint
 *
 * @api
 */
class CurlerPageRequest implements CurlerPageRequestInterface
{
    protected RequestInterface $NextRequest;
    /** @var mixed[]|null */
    protected ?array $NextQuery;

    /**
     * Creates a new CurlerPageRequest object
     *
     * @param mixed[]|null $nextQuery
     */
    public function __construct(
        RequestInterface $nextRequest,
        ?array $nextQuery = null
    ) {
        $this->NextRequest = $nextRequest;
        $this->NextQuery = $nextQuery;
    }

    /**
     * @inheritDoc
     */
    public function hasNextRequest(): bool
    {
        return true;
    }

    /**
     * @inheritDoc
     */
    public function getNextRequest(): RequestInterface
    {
        return $this->NextRequest;
    }

    /**
     * @inheritDoc
     */
    public function getNextQuery(): ?array
    {
        return $this->NextQuery;
    }
}
