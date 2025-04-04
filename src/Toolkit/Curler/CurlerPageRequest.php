<?php declare(strict_types=1);

namespace Salient\Curler;

use Psr\Http\Message\RequestInterface as PsrRequestInterface;
use Salient\Contract\Curler\CurlerPageRequestInterface;

/**
 * A request for a page of data from an HTTP endpoint
 *
 * @api
 */
class CurlerPageRequest implements CurlerPageRequestInterface
{
    protected PsrRequestInterface $NextRequest;
    /** @var mixed[]|null */
    protected ?array $NextQuery;

    /**
     * @api
     *
     * @param mixed[]|null $nextQuery
     */
    public function __construct(
        PsrRequestInterface $nextRequest,
        ?array $nextQuery = null
    ) {
        $this->NextRequest = $nextRequest;
        $this->NextQuery = $nextQuery;
    }

    /**
     * @inheritDoc
     */
    public function getRequest(): PsrRequestInterface
    {
        return $this->NextRequest;
    }

    /**
     * @inheritDoc
     */
    public function getQuery(): ?array
    {
        return $this->NextQuery;
    }
}
