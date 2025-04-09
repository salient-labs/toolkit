<?php declare(strict_types=1);

namespace Salient\Http;

use Salient\Contract\Http\HasInnerHeaders as HasHttpHeadersInterface;
use Salient\Contract\Http\HeadersInterface;
use Salient\Core\Concern\ImmutableTrait;

/**
 * @api
 *
 * @phpstan-require-implements HasHttpHeadersInterface
 */
trait HasHttpHeaders
{
    use ImmutableTrait;

    protected HeadersInterface $Headers;

    /**
     * @inheritDoc
     */
    public function getInnerHeaders(): HeadersInterface
    {
        return $this->Headers;
    }

    /**
     * @inheritDoc
     */
    public function getHeaders(): array
    {
        return $this->Headers->getHeaders();
    }

    /**
     * @inheritDoc
     */
    public function hasHeader(string $name): bool
    {
        return $this->Headers->hasHeader($name);
    }

    /**
     * @inheritDoc
     */
    public function getHeader(string $name): array
    {
        return $this->Headers->getHeader($name);
    }

    /**
     * @inheritDoc
     */
    public function getHeaderLine(string $name): string
    {
        return $this->Headers->getHeaderLine($name);
    }

    /**
     * @inheritDoc
     */
    public function getHeaderLines(): array
    {
        return $this->Headers->getHeaderLines();
    }

    /**
     * @inheritDoc
     */
    public function getHeaderValues(string $name): array
    {
        return $this->Headers->getHeaderValues($name);
    }

    /**
     * @inheritDoc
     */
    public function getFirstHeaderValue(string $name): string
    {
        return $this->Headers->getFirstHeaderValue($name);
    }

    /**
     * @inheritDoc
     */
    public function getLastHeaderValue(string $name): string
    {
        return $this->Headers->getLastHeaderValue($name);
    }

    /**
     * @inheritDoc
     */
    public function getOnlyHeaderValue(string $name, bool $orSame = false): string
    {
        return $this->Headers->getOnlyHeaderValue($name, $orSame);
    }

    /**
     * @return static
     */
    public function withHeader(string $name, $value): self
    {
        return $this->with('Headers', $this->Headers->set($name, $value));
    }

    /**
     * @return static
     */
    public function withAddedHeader(string $name, $value): self
    {
        return $this->with('Headers', $this->Headers->addValue($name, $value));
    }

    /**
     * @return static
     */
    public function withoutHeader(string $name): self
    {
        return $this->with('Headers', $this->Headers->unset($name));
    }
}
