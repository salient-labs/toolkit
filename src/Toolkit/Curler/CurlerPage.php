<?php declare(strict_types=1);

namespace Salient\Curler;

use Psr\Http\Message\RequestInterface;
use Salient\Contract\Curler\CurlerPageInterface;
use Salient\Core\Exception\OutOfRangeException;

/**
 * Data returned by an HTTP endpoint
 */
class CurlerPage implements CurlerPageInterface
{
    /** @var list<mixed> */
    protected array $Entities;
    protected ?RequestInterface $NextRequest;

    /**
     * Creates a new CurlerPage object
     *
     * @param list<mixed> $entities
     */
    public function __construct(
        array $entities,
        ?RequestInterface $nextRequest = null
    ) {
        $this->Entities = $entities;
        $this->NextRequest = $nextRequest;
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
    public function isLastPage(): bool
    {
        return $this->NextRequest === null;
    }

    /**
     * @inheritDoc
     */
    public function getNextRequest(): RequestInterface
    {
        if ($this->NextRequest === null) {
            throw new OutOfRangeException('No more pages');
        }
        return $this->NextRequest;
    }
}
