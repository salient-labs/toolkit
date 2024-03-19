<?php declare(strict_types=1);

namespace Salient\Curler\Support;

use Salient\Contract\Http\HttpHeadersInterface;
use Salient\Core\Exception\BadMethodCallException;
use Salient\Curler\Contract\ICurlerPage;
use Salient\Curler\Curler;

/**
 * Implements ICurlerPage
 */
final class CurlerPage implements ICurlerPage
{
    /**
     * @var mixed[]
     */
    private array $Entities;

    private int $EntityCount;

    private bool $IsLastPage;

    private string $NextUrl;

    /**
     * @var mixed[]|null
     */
    private ?array $NextData;

    private ?HttpHeadersInterface $NextHeaders;

    /**
     * @param mixed[] $entities Data extracted from the upstream response
     * @param Curler $curler The Curler instance that retrieved the page
     * @param string|null $nextUrl The URL of the next page, including the query
     * component (if any)
     * @param bool|null $isLastPage Set if no more data is available
     * @param mixed[]|null $nextData Data to send in the body of the next
     * request
     * @param HttpHeadersInterface|null $nextHeaders Replaces the next request's
     * HTTP headers
     */
    public function __construct(
        array $entities,
        Curler $curler,
        ?ICurlerPage $previous,
        ?string $nextUrl = null,
        ?bool $isLastPage = null,
        ?array $nextData = null,
        ?HttpHeadersInterface $nextHeaders = null
    ) {
        $this->Entities = $entities;
        $this->EntityCount = count($entities) + ($previous ? $previous->entityCount() : 0);
        $this->IsLastPage = $isLastPage ?? (string) $nextUrl === '';

        if (!$this->IsLastPage) {
            $this->NextUrl = $nextUrl ?? $curler->BaseUrl . $curler->QueryString;
            $this->NextData = $nextData;
            $this->NextHeaders = $nextHeaders;
        }
    }

    /**
     * @inheritDoc
     */
    public function entities(): array
    {
        return $this->Entities;
    }

    /**
     * @inheritDoc
     */
    public function entityCount(): int
    {
        return $this->EntityCount;
    }

    /**
     * @inheritDoc
     */
    public function isLastPage(): bool
    {
        return $this->IsLastPage;
    }

    /**
     * @inheritDoc
     */
    private function assertHasNextPage(): void
    {
        if ($this->IsLastPage) {
            throw new BadMethodCallException('No more pages');
        }
    }

    /**
     * @inheritDoc
     */
    public function nextUrl(): string
    {
        $this->assertHasNextPage();

        return $this->NextUrl;
    }

    /**
     * @inheritDoc
     */
    public function nextData(): ?array
    {
        $this->assertHasNextPage();

        return $this->NextData;
    }

    /**
     * @inheritDoc
     */
    public function nextHeaders(): ?HttpHeadersInterface
    {
        $this->assertHasNextPage();

        return $this->NextHeaders;
    }
}
