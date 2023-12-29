<?php declare(strict_types=1);

namespace Lkrms\Http;

use Lkrms\Exception\InvalidArgumentException;
use Lkrms\Exception\InvalidArgumentTypeException;
use Lkrms\Http\Exception\StreamDetachedException;
use Lkrms\Http\Exception\StreamInvalidRequestException;
use Lkrms\Utility\File;
use Lkrms\Utility\Str;
use Psr\Http\Message\StreamInterface;
use Stringable;

/**
 * A PHP stream wrapper
 */
class Stream implements StreamInterface, Stringable
{
    /**
     * @var resource|null
     */
    protected $Stream;

    protected ?string $Uri;

    protected bool $IsReadable;

    protected bool $IsWritable;

    protected bool $IsSeekable;

    protected ?int $Size = null;

    /**
     * Creates a new Stream object
     *
     * @param resource $stream
     */
    public function __construct($stream)
    {
        if (!is_resource($stream) || get_resource_type($stream) !== 'stream') {
            throw new InvalidArgumentTypeException(1, 'stream', 'resource', $stream);
        }

        $meta = stream_get_meta_data($stream);

        $this->Stream = $stream;
        // @phpstan-ignore-next-line
        $this->Uri = $meta['uri'] ?? null;
        $this->IsReadable = strpbrk($meta['mode'], 'r+') !== false;
        $this->IsWritable = strpbrk($meta['mode'], 'waxc+') !== false;
        $this->IsSeekable = $meta['seekable'];
    }

    /**
     * Creates a new Stream object from a string
     */
    public static function fromString(string $content): self
    {
        return new self(Str::toStream($content));
    }

    /**
     * @inheritDoc
     */
    public function isReadable(): bool
    {
        return $this->IsReadable;
    }

    /**
     * @inheritDoc
     */
    public function isWritable(): bool
    {
        return $this->IsWritable;
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
    public function getSize(): ?int
    {
        if ($this->Size !== null) {
            return $this->Size;
        }

        if (!$this->Stream) {
            return null;
        }

        if ($this->Uri !== null) {
            clearstatcache(true, $this->Uri);
        }

        $this->Size = File::stat($this->Stream, $this->Uri)['size'] ?? null;

        return $this->Size;
    }

    /**
     * @inheritDoc
     */
    public function getMetadata(?string $key = null)
    {
        if (!$this->Stream) {
            return $key === null ? [] : null;
        }

        $meta = stream_get_meta_data($this->Stream);

        return $key === null ? $meta : ($meta[$key] ?? null);
    }

    /**
     * @inheritDoc
     */
    public function __toString(): string
    {
        if ($this->IsSeekable) {
            $this->seek(0);
        }

        return $this->getContents();
    }

    /**
     * @inheritDoc
     */
    public function getContents(): string
    {
        $this->assertIsReadable();

        return File::getContents($this->Stream, $this->Uri);
    }

    /**
     * @inheritDoc
     */
    public function tell(): int
    {
        $this->assertHasStream();

        return File::tell($this->Stream, $this->Uri);
    }

    /**
     * @inheritDoc
     */
    public function eof(): bool
    {
        $this->assertHasStream();

        return feof($this->Stream);
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
        $this->assertIsReadable();

        if ($length === 0) {
            return '';
        }

        if ($length < 0) {
            throw new InvalidArgumentException('Argument #1 ($length) must be greater than or equal to 0');
        }

        return File::read($this->Stream, $length, $this->Uri);
    }

    /**
     * @inheritDoc
     */
    public function write(string $string): int
    {
        $this->assertHasStream();

        if (!$this->IsWritable) {
            throw new StreamInvalidRequestException('Stream is not open for writing');
        }

        $this->Size = null;

        return File::write($this->Stream, $string, null, $this->Uri);
    }

    /**
     * @inheritDoc
     *
     * @param \SEEK_SET|\SEEK_CUR|\SEEK_END $whence
     */
    public function seek(int $offset, int $whence = \SEEK_SET): void
    {
        $this->assertHasStream();

        if (!$this->IsSeekable) {
            throw new StreamInvalidRequestException('Stream is not seekable');
        }

        File::seek($this->Stream, $offset, $whence, $this->Uri);
    }

    /**
     * @inheritDoc
     */
    public function close(): void
    {
        if (!$this->Stream) {
            return;
        }

        File::close($this->Stream, $this->Uri);
        $this->detach();
    }

    /**
     * @inheritDoc
     */
    public function detach()
    {
        if (!$this->Stream) {
            return null;
        }

        $result = $this->Stream;

        $this->Stream = null;
        $this->Uri = null;
        $this->IsReadable = false;
        $this->IsWritable = false;
        $this->IsSeekable = false;
        $this->Size = null;

        return $result;
    }

    protected function assertIsReadable(): void
    {
        $this->assertHasStream();

        if (!$this->IsReadable) {
            throw new StreamInvalidRequestException('Stream is not open for reading');
        }
    }

    protected function assertHasStream(): void
    {
        if (!$this->Stream) {
            throw new StreamDetachedException('Stream is detached');
        }
    }
}
