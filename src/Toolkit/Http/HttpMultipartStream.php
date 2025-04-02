<?php declare(strict_types=1);

namespace Salient\Http;

use Psr\Http\Message\StreamInterface;
use Salient\Contract\Http\Message\HttpMultipartStreamInterface;
use Salient\Contract\Http\Message\HttpMultipartStreamPartInterface;
use Salient\Contract\Http\HttpHeader;
use Salient\Http\Exception\StreamException;
use Salient\Http\Exception\StreamInvalidRequestException;
use Salient\Utility\Regex;
use InvalidArgumentException;
use Throwable;

/**
 * A PSR-7 multipart data stream wrapper
 */
class HttpMultipartStream implements HttpMultipartStreamInterface
{
    protected const CHUNK_SIZE = 8192;

    protected string $Boundary;
    protected bool $IsSeekable = true;
    /** @var StreamInterface[] */
    protected array $Streams = [];
    protected int $Stream = 0;
    protected int $Pos = 0;

    /**
     * @param HttpMultipartStreamPartInterface[] $parts
     */
    public function __construct(array $parts = [], ?string $boundary = null)
    {
        $this->Boundary = $boundary ??= '------' . bin2hex(random_bytes(18));
        $this->applyParts($parts);
    }

    /**
     * @param HttpMultipartStreamPartInterface[] $parts
     */
    private function applyParts(array $parts): void
    {
        foreach ($parts as $part) {
            $contentStream = $part->getContent();

            if (!$contentStream->isReadable()) {
                throw new InvalidArgumentException('Stream must be readable');
            }

            // For the stream to be seekable, every part must be seekable
            if ($this->IsSeekable && !$contentStream->isSeekable()) {
                $this->IsSeekable = false;
            }

            $disposition = [
                'form-data',
                sprintf('name="%s"', HttpUtil::escapeQuotedString($part->getName())),
            ];
            $fallbackFilename = $part->getFallbackFilename();
            if ($fallbackFilename !== null) {
                $disposition[] = sprintf(
                    'filename="%s"',
                    HttpUtil::escapeQuotedString($fallbackFilename),
                );
            }
            $filename = $part->getFilename();
            if ($filename !== null && $filename !== $fallbackFilename) {
                $disposition[] = sprintf(
                    "filename*=UTF-8''%s",
                    $this->encode($filename),
                );
            }
            $headers = new HttpHeaders([
                HttpHeader::CONTENT_DISPOSITION => implode('; ', $disposition),
            ]);
            $mediaType = $part->getMediaType();
            if ($mediaType !== null) {
                $headers = $headers->set(HttpHeader::CONTENT_TYPE, $mediaType);
            }
            $headers = sprintf(
                "--%s\r\n%s\r\n\r\n",
                $this->Boundary,
                (string) $headers,
            );

            $this->Streams[] = HttpStream::fromString($headers);
            $this->Streams[] = $contentStream;
            $this->Streams[] = HttpStream::fromString("\r\n");
        }

        $this->Streams[] = HttpStream::fromString(sprintf("--%s--\r\n", $this->Boundary));
    }

    /**
     * @inheritDoc
     */
    public function isReadable(): bool
    {
        return true;
    }

    /**
     * @inheritDoc
     */
    public function isWritable(): bool
    {
        return false;
    }

    /**
     * @inheritDoc
     */
    public function isSeekable(): bool
    {
        return $this->IsSeekable;
    }

    /**
     * @inheritDoc
     */
    public function getBoundary(): string
    {
        return $this->Boundary;
    }

    /**
     * @inheritDoc
     */
    public function getSize(): ?int
    {
        $total = 0;
        foreach ($this->Streams as $stream) {
            $size = $stream->getSize();
            if ($size === null) {
                // @codeCoverageIgnoreStart
                return null;
                // @codeCoverageIgnoreEnd
            }
            $total += $size;
        }
        return $total;
    }

    /**
     * @inheritDoc
     */
    public function getMetadata(?string $key = null)
    {
        return $key === null ? [] : null;
    }

    /**
     * @inheritDoc
     */
    public function __toString(): string
    {
        if ($this->IsSeekable) {
            $this->rewind();
        }
        return $this->getContents();
    }

    /**
     * @inheritDoc
     */
    public function getContents(): string
    {
        return HttpStream::copyToString($this);
    }

    /**
     * @inheritDoc
     */
    public function tell(): int
    {
        return $this->Pos;
    }

    /**
     * @inheritDoc
     */
    public function eof(): bool
    {
        return !$this->Streams
            || ($this->Stream >= count($this->Streams) - 1
                && $this->Streams[$this->Stream]->eof());
    }

    /**
     * @inheritDoc
     */
    public function rewind(): void
    {
        $this->seek(0);
    }

    /**
     * @inheritDoc
     */
    public function read(int $length): string
    {
        if ($length === 0) {
            return '';
        }

        if ($length < 0) {
            throw new InvalidArgumentException('Argument #1 ($length) must be greater than or equal to 0');
        }

        $buffer = '';
        $remaining = $length;
        $last = count($this->Streams) - 1;
        $eof = false;

        while ($remaining > 0) {
            if ($eof || $this->Streams[$this->Stream]->eof()) {
                if ($this->Stream < $last) {
                    $eof = false;
                    $this->Stream++;
                } else {
                    break;
                }
            }

            $data = $this->Streams[$this->Stream]->read($remaining);

            if ($data === '') {
                $eof = true;
                continue;
            }

            $bytes = strlen($data);

            $buffer .= $data;
            $remaining -= $bytes;
            $this->Pos += $bytes;
        }

        return $buffer;
    }

    /**
     * @inheritDoc
     */
    public function write(string $string): int
    {
        throw new StreamInvalidRequestException('Stream is not writable');
    }

    /**
     * @inheritDoc
     */
    public function seek(int $offset, int $whence = \SEEK_SET): void
    {
        if (!$this->IsSeekable) {
            throw new StreamInvalidRequestException('Stream is not seekable');
        }

        switch ($whence) {
            case \SEEK_SET:
                if ($offset === $this->Pos) {
                    return;
                }
                $offsetPos = $offset;
                $relativeTo = 0;
                break;

            case \SEEK_END:
                $this->getContents();
                // No break
            case \SEEK_CUR:
                if ($offset === 0) {
                    return;
                }
                $offsetPos = $this->Pos + $offset;
                $relativeTo = $this->Pos;
                break;

            default:
                // @codeCoverageIgnoreStart
                throw new InvalidArgumentException(
                    sprintf('Invalid whence: %d', $whence)
                );
                // @codeCoverageIgnoreEnd
        }

        if ($offsetPos < 0) {
            throw new InvalidArgumentException(sprintf(
                'Invalid offset relative to position %d: %d',
                $relativeTo,
                $offset,
            ));
        }

        $this->Stream = 0;
        $this->Pos = 0;

        foreach ($this->Streams as $i => $stream) {
            try {
                $stream->rewind();
                // @codeCoverageIgnoreStart
            } catch (Throwable $ex) {
                throw new StreamException(sprintf('Error seeking stream %d', $i), $ex);
            }
            // @codeCoverageIgnoreEnd
        }

        while ($this->Pos < $offsetPos && !$this->eof()) {
            $data = $this->read(min(static::CHUNK_SIZE, $offsetPos - $this->Pos));
            if ($data === '') {
                // @codeCoverageIgnoreStart
                break;
                // @codeCoverageIgnoreEnd
            }
        }
    }

    /**
     * @inheritDoc
     */
    public function close(): void
    {
        foreach ($this->Streams as $stream) {
            $stream->close();
        }
        $this->reset();
    }

    /**
     * @inheritDoc
     */
    public function detach()
    {
        foreach ($this->Streams as $stream) {
            $stream->detach();
        }
        $this->reset();
        return null;
    }

    private function reset(): void
    {
        $this->IsSeekable = true;
        $this->Streams = [];
        $this->Stream = 0;
        $this->Pos = 0;
        $this->applyParts([]);
    }

    /**
     * Percent-encode characters as per [RFC5987] Section 3.2 ("Parameter Value
     * Character Set and Language Information")
     */
    private function encode(string $string): string
    {
        return Regex::replaceCallback(
            '/[^!#$&+^`|]++/',
            fn(array $matches) => rawurlencode($matches[0]),
            $string,
        );
    }
}
