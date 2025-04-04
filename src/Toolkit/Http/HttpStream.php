<?php declare(strict_types=1);

namespace Salient\Http;

use Psr\Http\Message\StreamInterface as PsrStreamInterface;
use Salient\Contract\Core\DateFormatterInterface;
use Salient\Contract\Http\Message\StreamInterface;
use Salient\Contract\Http\Message\StreamPartInterface;
use Salient\Contract\Http\HasFormDataFlag;
use Salient\Http\Exception\StreamDetachedException;
use Salient\Http\Exception\StreamEncapsulationException;
use Salient\Http\Exception\StreamInvalidRequestException;
use Salient\Utility\Exception\InvalidArgumentTypeException;
use Salient\Utility\File;
use Salient\Utility\Json;
use Salient\Utility\Str;
use InvalidArgumentException;

/**
 * A PSR-7 stream wrapper
 */
class HttpStream implements StreamInterface, HasFormDataFlag
{
    protected const CHUNK_SIZE = 8192;

    protected ?string $Uri;
    protected bool $IsReadable;
    protected bool $IsWritable;
    protected bool $IsSeekable;
    /** @var resource|null */
    protected $Stream;

    /**
     * @param resource $stream
     */
    public function __construct($stream)
    {
        if (!File::isStream($stream)) {
            throw new InvalidArgumentTypeException(1, 'stream', 'resource (stream)', $stream);
        }

        $meta = stream_get_meta_data($stream);

        $this->Uri = $meta['uri'] ?? null;
        $this->IsReadable = strpbrk($meta['mode'], 'r+') !== false;
        $this->IsWritable = strpbrk($meta['mode'], 'waxc+') !== false;
        $this->IsSeekable = $meta['seekable'];
        $this->Stream = $stream;
    }

    /**
     * @internal
     */
    public function __destruct()
    {
        $this->close();
    }

    /**
     * Creates a new HttpStream object from a string
     */
    public static function fromString(string $content): self
    {
        return new self(Str::toStream($content));
    }

    /**
     * Encapsulate arbitrarily nested data in a new HttpStream or
     * HttpMultipartStream object
     *
     * @param mixed[]|object $data
     * @param int-mask-of<HttpStream::DATA_*> $flags
     */
    public static function fromData(
        $data,
        int $flags = HttpStream::DATA_PRESERVE_NUMERIC_KEYS | HttpStream::DATA_PRESERVE_STRING_KEYS,
        ?DateFormatterInterface $dateFormatter = null,
        bool $asJson = false,
        ?string $boundary = null
    ): StreamInterface {
        $formData = new FormData($data);
        if ($asJson) {
            $callback = static function (object $value) {
                if ($value instanceof StreamPartInterface) {
                    throw new StreamEncapsulationException('Multipart data streams cannot be JSON-encoded');
                }
                return false;
            };
            $data = $formData->getData($flags, $dateFormatter, $callback);
            return self::fromString(Json::encode($data));
        }

        $multipart = false;
        $callback = static function (object $value) use (&$multipart) {
            if ($value instanceof StreamPartInterface) {
                $multipart = true;
                return $value;
            }
            return false;
        };
        $data = $formData->getValues($flags, $dateFormatter, $callback);

        if (!$multipart) {
            /** @var string $content */
            foreach ($data as [$name, $content]) {
                $query[] = rawurlencode($name) . '=' . rawurlencode($content);
            }
            return self::fromString(implode('&', $query ?? []));
        }

        /** @var string|StreamPartInterface $content */
        foreach ($data as [$name, $content]) {
            if ($content instanceof StreamPartInterface) {
                $parts[] = $content->withName($name);
            } else {
                $parts[] = new HttpMultipartStreamPart($content, $name);
            }
        }
        return new HttpMultipartStream($parts ?? [], $boundary);
    }

    /**
     * Copy data from a stream to a string
     */
    public static function copyToString(PsrStreamInterface $from): string
    {
        $out = '';
        while (!$from->eof()) {
            $in = $from->read(static::CHUNK_SIZE);
            if ($in === '') {
                // @codeCoverageIgnoreStart
                break;
                // @codeCoverageIgnoreEnd
            }
            $out .= $in;
        }
        return $out;
    }

    /**
     * Copy data from one stream to another
     */
    public static function copyToStream(PsrStreamInterface $from, PsrStreamInterface $to): void
    {
        $out = '';
        while (!$from->eof()) {
            $in = $from->read(static::CHUNK_SIZE);
            if ($in === '') {
                break;
            }
            $out .= $in;
            $out = substr($out, $to->write($out));
        }
        while ($out !== '') {
            // @codeCoverageIgnoreStart
            $out = substr($out, $to->write($out));
            // @codeCoverageIgnoreEnd
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
            $this->rewind();
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
        $this->assertIsSeekable();

        File::rewind($this->Stream, $this->Uri);
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
        $this->assertIsSeekable();

        /** @disregard P1006 */
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
     * @phpstan-assert true $this->IsSeekable
     */
    protected function assertIsSeekable(): void
    {
        $this->assertHasStream();

        if (!$this->IsSeekable) {
            throw new StreamInvalidRequestException('Stream is not seekable');
        }
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
