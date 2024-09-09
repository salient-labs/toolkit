<?php declare(strict_types=1);

namespace Salient\Curler;

use Psr\Http\Message\RequestInterface;
use Salient\Contract\Curler\CurlerPageInterface;
use OutOfRangeException;

/**
 * A page of data returned by an HTTP endpoint
 *
 * @api
 */
class CurlerPage implements CurlerPageInterface
{
    /** @var list<mixed> */
    protected array $Entities;
    protected ?int $Current;
    protected ?int $Total;
    protected ?CurlerPageRequest $NextRequest;

    /**
     * Creates a new CurlerPage object
     *
     * `$current` and `$total`, together with {@see CurlerPage::getCurrent()}
     * and {@see CurlerPage::getTotal()}, allow pagers to track progress across
     * responses if necessary.
     *
     * @param list<mixed> $entities
     * @param mixed[]|null $nextQuery
     */
    public function __construct(
        array $entities,
        ?RequestInterface $nextRequest = null,
        ?array $nextQuery = null,
        ?int $current = null,
        ?int $total = null
    ) {
        $this->Entities = $entities;
        $this->Current = $current;
        $this->Total = $total;
        $this->NextRequest = $nextRequest
            ? new CurlerPageRequest($nextRequest, $nextQuery)
            : null;
    }

    /**
     * @inheritDoc
     */
    public function getEntities(): array
    {
        return $this->Entities;
    }

    /**
     * @inheritDoc
     */
    public function hasNextRequest(): bool
    {
        return $this->NextRequest !== null;
    }

    /**
     * @inheritDoc
     */
    public function getNextRequest(): RequestInterface
    {
        if ($this->NextRequest === null) {
            throw new OutOfRangeException('No more pages');
        }
        return $this->NextRequest->getNextRequest();
    }

    /**
     * @inheritDoc
     */
    public function getNextQuery(): ?array
    {
        if ($this->NextRequest === null) {
            throw new OutOfRangeException('No more pages');
        }
        return $this->NextRequest->getNextQuery();
    }

    /**
     * Get the value of $current passed to the constructor
     */
    public function getCurrent(): ?int
    {
        return $this->Current;
    }

    /**
     * Get the value of $total passed to the constructor
     */
    public function getTotal(): ?int
    {
        return $this->Total;
    }
}
