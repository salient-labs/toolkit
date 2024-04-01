<?php declare(strict_types=1);

namespace Salient\Http;

use Psr\Http\Message\StreamInterface;
use Salient\Contract\Http\HttpStreamInterface;
use Salient\Core\Exception\InvalidArgumentException;
use Salient\Core\Exception\InvalidArgumentTypeException;
use Salient\Core\Utility\File;
use Salient\Core\Utility\Inflect;
use Salient\Core\Utility\Str;
use Salient\Http\Exception\StreamDetachedException;
use Salient\Http\Exception\StreamException;
use Salient\Http\Exception\StreamInvalidRequestException;

/**
 * A PHP stream wrapper
 *
 * @api
 */
class HttpStream implements HttpStreamInterface
{
    protected const CHUNK_SIZE = 8192;

    protected ?string $Uri;
    protected bool $IsReadable;
    protected bool $IsWritable;
    protected bool $IsSeekable;

    /**
     * @var resource|null
     */
    protected $Stream;

    /**
     * Creates a new HttpStream object
     *
     * @param resource $stream
     */
    public function __construct($stream)
    {
        if (!File::isStream($stream)) {
            throw new InvalidArgumentTypeException(1, 'stream', 'resource (stream)', $stream);
        }

        $meta = stream_get_meta_data($stream);

        // @phpstan-ignore-next-line
        $this->Uri = $meta['uri'] ?? null;
        $this->IsReadable = strpbrk($meta['mode'], 'r+') !== false;
        $this->IsWritable = strpbrk($meta['mode'], 'waxc+') !== false;
        $this->IsSeekable = $meta['seekable'];
        $this->Stream = $stream;
    }

    public function __destruct()
    {
        $this->close();
    }

    /**
     * @inheritDoc
     */
    public static function getMediaType(): ?string
    {
        return null;
    }

    /**
     * Creates a new HttpStream object from a string
     */
    public static function fromString(string $content): self
    {
        return new self(Str::toStream($content));
    }

    /**
     * Copy data from one stream to another
     */
    public static function copy(StreamInterface $from, StreamInterface $to): void
    {
        while (!$from->eof()) {
            $in = $from->read(static::CHUNK_SIZE);
            $written = $to->write($in);
            $unwritten = strlen($in) - $written;
            assert($unwritten >= 0);
            if ($unwritten > 0) {
                // @codeCoverageIgnoreStart
                throw new StreamException(Inflect::format(
                    $unwritten,
                    'Error copying data to stream: {{#}} {{#:byte}} not written',
                ));
                // @codeCoverageIgnoreEnd
            }
        }
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
        $this->assertHasStream();

        clearstatcache();

        return File::stat($this->Stream, $this->Uri)['size'] ?? null;
    }

    /**
     * @inheritDoc
     */
    public function getMetadata(?string $key = null)
    {
        $this->assertHasStream();

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

        return File::getContents($this->Stream, null, $this->Uri);
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

        return @feof($this->Stream);
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

        return File::write($this->Stream, $string, null, $this->Uri);
    }

    /**
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

        return $result;
    }

    /**
     * @phpstan-assert !null $this->Stream
     * @phpstan-assert true $this->IsReadable
     */
    protected function assertIsReadable(): void
    {
        $this->assertHasStream();

        if (!$this->IsReadable) {
            throw new StreamInvalidRequestException('Stream is not open for reading');
        }
    }

    /**
     * @phpstan-assert !null $this->Stream
     */
    protected function assertHasStream(): void
    {
        if (!$this->Stream) {
            throw new StreamDetachedException('Stream is detached');
        }
    }
}
