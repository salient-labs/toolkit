<?php declare(strict_types=1);

namespace Salient\Http\Message;

use Psr\Http\Message\StreamInterface as PsrStreamInterface;
use Salient\Contract\Http\Message\MultipartStreamInterface;
use Salient\Contract\Http\Message\StreamPartInterface;
use Salient\Http\Exception\InvalidStreamRequestException;
use Salient\Http\Exception\StreamClosedException;
use Salient\Http\Headers;
use Salient\Http\HttpUtil;
use Salient\Utility\Regex;
use InvalidArgumentException;

/**
 * @api
 */
class MultipartStream implements MultipartStreamInterface
{
    protected const CHUNK_SIZE = 8192;

    /** @var StreamPartInterface[] */
    private array $Parts;
    private string $Boundary;
    private bool $IsSeekable = true;
    /** @var PsrStreamInterface[] */
    private array $Streams = [];
    private int $Stream = 0;
    private int $Pos = 0;
    private bool $IsOpen = true;

    /**
     * @api
     *
     * @param StreamPartInterface[] $parts
     */
    public function __construct(array $parts = [], ?string $boundary = null)
    {
        $this->Parts = $parts;
        $this->Boundary = $boundary ??= '------' . bin2hex(random_bytes(18));

        foreach ($parts as $key => $part) {
            $body = $part->getBody();
            if (!$body->isReadable()) {
                throw new InvalidArgumentException(
                    sprintf('Body not readable: %s', $key),
                );
            }
            if ($this->IsSeekable && !$body->isSeekable()) {
                $this->IsSeekable = false;
            }

            $name = $part->getName();
            $filename = $part->getFilename();
            $asciiFilename = $part->getAsciiFilename();
            $mediaType = $part->getMediaType();

            $disposition = ['form-data'];
            $disposition[] = sprintf(
                'name=%s',
                HttpUtil::quoteString($name),
            );
            if ($asciiFilename !== null) {
                $disposition[] = sprintf(
                    'filename=%s',
                    HttpUtil::quoteString($asciiFilename),
                );
            }
            if ($filename !== null && $filename !== $asciiFilename) {
                $disposition[] = sprintf(
                    "filename*=UTF-8''%s",
                    // Percent-encode as per [RFC8187] Section 3.2 ("Parameter
                    // Value Character Encoding and Language Information")
                    Regex::replaceCallback(
                        // HTTP token characters except "*", "'", "%" and those
                        // left alone by `rawurlencode()`
                        '/[^!#$&+^`|]++/',
                        fn($matches) => rawurlencode($matches[0]),
                        $filename,
                    ),
                );
            }
            $headers = [
                self::HEADER_CONTENT_DISPOSITION => implode('; ', $disposition),
            ];
            if ($mediaType !== null) {
                $headers[self::HEADER_CONTENT_TYPE] = $mediaType;
            }
            $this->Streams[] = Stream::fromString(sprintf(
                "--%s\r\n%s\r\n\r\n",
                $boundary,
                (string) new Headers($headers),
            ));
            $this->Streams[] = $body;
            $this->Streams[] = Stream::fromString("\r\n");
        }

        $this->Streams[] = Stream::fromString(sprintf(
            "--%s--\r\n",
            $boundary,
        ));
    }

    /**
     * @inheritDoc
     */
    public function isReadable(): bool
    {
        return $this->IsOpen;
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
    public function getParts(): array
    {
        $this->assertIsOpen();

        return $this->Parts;
    }

    /**
     * @inheritDoc
     */
    public function getBoundary(): string
    {
        $this->assertIsOpen();

        return $this->Boundary;
    }

    /**
     * @inheritDoc
     */
    public function getSize(): ?int
    {
        $this->assertIsOpen();

        $total = 0;
        foreach ($this->Streams as $stream) {
            $size = $stream->getSize();
            if ($size === null) {
                return null;
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
        $this->assertIsOpen();

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
        $this->assertIsOpen();

        return HttpUtil::getStreamContents($this);
    }

    /**
     * @inheritDoc
     */
    public function tell(): int
    {
        $this->assertIsOpen();

        return $this->Pos;
    }

    /**
     * @inheritDoc
     */
    public function eof(): bool
    {
        $this->assertIsOpen();

        return $this->Stream === count($this->Streams) - 1
            && $this->Streams[$this->Stream]->eof();
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
        $this->assertIsOpen();

        if ($length === 0) {
            return '';
        }

        if ($length < 0) {
            throw new InvalidArgumentException('Argument #1 ($length) must be greater than or equal to 0');
        }

        $buffer = '';
        $lastStream = count($this->Streams) - 1;
        $eof = false;

        while ($length > 0) {
            if ($eof || $this->Streams[$this->Stream]->eof()) {
                if ($this->Stream < $lastStream) {
                    $eof = false;
                    $this->Stream++;
                } else {
                    break;
                }
            }

            $data = $this->Streams[$this->Stream]->read($length);

            if ($data === '') {
                $eof = true;
                continue;
            }

            $dataLength = strlen($data);

            $buffer .= $data;
            $length -= $dataLength;
            $this->Pos += $dataLength;
        }

        return $buffer;
    }

    /**
     * @inheritDoc
     */
    public function write(string $string): int
    {
        $this->assertIsOpen();

        throw new InvalidStreamRequestException('Stream is not writable');
    }

    /**
     * @inheritDoc
     */
    public function seek(int $offset, int $whence = \SEEK_SET): void
    {
        $this->assertIsOpen();

        if (!$this->IsSeekable) {
            throw new InvalidStreamRequestException('Stream is not seekable');
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
                throw new InvalidArgumentException(
                    sprintf('Invalid whence: %d', $whence),
                );
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

        foreach ($this->Streams as $stream) {
            $stream->rewind();
        }

        while ($this->Pos < $offsetPos && !$this->eof()) {
            $this->read(min(static::CHUNK_SIZE, $offsetPos - $this->Pos));
        }
    }

    /**
     * @inheritDoc
     */
    public function close(): void
    {
        if (!$this->IsOpen) {
            return;
        }

        foreach ($this->Streams as $stream) {
            $stream->close();
        }

        $this->doClose();
    }

    /**
     * @inheritDoc
     */
    public function detach()
    {
        if (!$this->IsOpen) {
            return null;
        }

        foreach ($this->Streams as $stream) {
            $stream->detach();
        }

        $this->doClose();
        return null;
    }

    private function doClose(): void
    {
        $this->IsOpen = false;
        $this->IsSeekable = false;
        $this->Parts = [];
        $this->Streams = [];
    }

    private function assertIsOpen(): void
    {
        if (!$this->IsOpen) {
            throw new StreamClosedException('Stream is closed or detached');
        }
    }
}
