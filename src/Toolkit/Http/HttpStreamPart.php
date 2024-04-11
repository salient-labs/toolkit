<?php declare(strict_types=1);

namespace Salient\Http;

use Psr\Http\Message\StreamInterface;
use Salient\Contract\Http\HttpStreamPartInterface;
use Salient\Core\Exception\InvalidArgumentException;
use Salient\Core\Exception\InvalidArgumentTypeException;
use Salient\Core\Utility\Pcre;
use Salient\Core\Utility\Str;
use Salient\Core\Utility\Test;

/**
 * Part of a multipart stream
 */
class HttpStreamPart implements HttpStreamPartInterface
{
    protected string $Name;
    protected ?string $Filename;
    protected ?string $FallbackFilename;
    protected ?string $MediaType;
    protected StreamInterface $Content;

    /**
     * @param StreamInterface|resource|string|null $content
     */
    public function __construct(
        string $name,
        $content,
        ?string $filename = null,
        ?string $mediaType = null,
        ?string $fallbackFilename = null
    ) {
        $this->Name = $name;
        $this->Filename = Str::coalesce($filename, null);
        $this->FallbackFilename = $this->filterFallbackFilename(
            Str::coalesce($fallbackFilename, null),
            $this->Filename
        );
        $this->MediaType = Str::coalesce($mediaType, null);
        $this->Content = $this->filterContent($content);
    }

    /**
     * @inheritDoc
     */
    public function getName(): string
    {
        return $this->Name;
    }

    /**
     * @inheritDoc
     */
    public function getFilename(): ?string
    {
        return $this->Filename;
    }

    /**
     * @inheritDoc
     */
    public function getFallbackFilename(): ?string
    {
        return $this->FallbackFilename;
    }

    /**
     * @inheritDoc
     */
    public function getMediaType(): ?string
    {
        return $this->MediaType;
    }

    /**
     * @inheritDoc
     */
    public function getContent(): StreamInterface
    {
        return $this->Content;
    }

    /**
     * Get $filename if $fallbackFilename is null and $filename is valid per
     * [RFC6266] Appendix D, $fallbackFilename otherwise
     *
     * @throws InvalidArgumentException if `$fallbackFilename` is not a valid
     * ASCII string.
     */
    private function filterFallbackFilename(?string $fallbackFilename, ?string $filename): ?string
    {
        $filename = $fallbackFilename ?? $filename;
        if ($filename === null) {
            return null;
        }
        if (
            !Test::isAsciiString($filename) ||
            Pcre::match('/%[0-9a-f]{2}|\\\\|"/i', $filename)
        ) {
            if ($fallbackFilename === null) {
                return null;
            }
            throw new InvalidArgumentException(
                sprintf('Invalid fallback filename: %s', $filename)
            );
        }
        return $filename;
    }

    /**
     * @param StreamInterface|resource|string|null $content
     */
    private function filterContent($content): StreamInterface
    {
        if ($content instanceof StreamInterface) {
            return $content;
        }
        if (is_string($content) || $content === null) {
            return HttpStream::fromString((string) $content);
        }
        try {
            return new HttpStream($content);
        } catch (InvalidArgumentException $ex) {
            throw new InvalidArgumentTypeException(
                1,
                'content',
                'StreamInterface|resource|string|null',
                $content
            );
        }
    }
}
