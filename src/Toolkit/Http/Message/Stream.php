<?php declare(strict_types=1);

namespace Salient\Http\Message;

use Salient\Contract\Core\DateFormatterInterface;
use Salient\Contract\Http\Message\StreamInterface;
use Salient\Contract\Http\Message\StreamPartInterface;
use Salient\Contract\Http\HasFormDataFlag;
use Salient\Http\Exception\InvalidStreamRequestException;
use Salient\Http\Exception\StreamClosedException;
use Salient\Http\Exception\StreamEncapsulationException;
use Salient\Http\Internal\FormDataEncoder;
use Salient\Utility\Exception\InvalidArgumentTypeException;
use Salient\Utility\File;
use Salient\Utility\Json;
use Salient\Utility\Str;
use InvalidArgumentException;

/**
 * @api
 */
class Stream implements StreamInterface, HasFormDataFlag
{
    private ?string $Uri;
    private bool $IsReadable;
    private bool $IsWritable;
    private bool $IsSeekable;
    /** @var resource|null */
    private $Stream;

    /**
     * @api
     *
     * @param resource $stream
     */
    final public function __construct($stream)
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
     * Get an instance from a string
     *
     * @return static
     */
    public static function fromString(string $content): self
    {
        return new static(Str::toStream($content));
    }

    /**
     * Get an instance from nested arrays and objects
     *
     * @param mixed[]|object $data
     * @param int-mask-of<Stream::DATA_*> $flags
     * @return static|MultipartStream
     */
    public static function fromData(
        $data,
        int $flags = Stream::DATA_PRESERVE_NUMERIC_KEYS | Stream::DATA_PRESERVE_STRING_KEYS,
        ?DateFormatterInterface $dateFormatter = null,
        bool $asJson = false,
        ?string $boundary = null
    ) {
        if ($asJson) {
            $callback = static function ($value) {
                if ($value instanceof StreamPartInterface) {
                    throw new StreamEncapsulationException(
                        'Multipart streams cannot be JSON-encoded',
                    );
                }
                return false;
            };
            $data = (new FormDataEncoder($flags, $dateFormatter, $callback))->getData($data);
            return static::fromString(Json::encode($data));
        }

        $multipart = false;
        $callback = static function ($value) use (&$multipart) {
            if ($value instanceof StreamPartInterface) {
                $multipart = true;
                return $value;
            }
            return false;
        };
        $data = (new FormDataEncoder($flags, $dateFormatter, $callback))->getValues($data);

        if (!$multipart) {
            /** @var string $content */
            foreach ($data as [$name, $content]) {
                $query[] = rawurlencode($name) . '=' . rawurlencode($content);
            }
            return static::fromString(implode('&', $query ?? []));
        }

        /** @var string|StreamPartInterface $content */
        foreach ($data as [$name, $content]) {
            if ($content instanceof StreamPartInterface) {
                $parts[] = $content->withName($name);
            } else {
                $parts[] = new StreamPart($content, $name);
            }
        }
        return new MultipartStream($parts ?? [], $boundary);
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

        return File::eof($this->Stream);
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

        if ($length < 0) {
            throw new InvalidArgumentException(
                'Argument #1 ($length) must be greater than or equal to 0',
            );
        }

        return $length
            ? File::read($this->Stream, $length, $this->Uri)
            : '';
    }

    /**
     * @inheritDoc
     */
    public function write(string $string): int
    {
        $this->assertHasStream();

        if (!$this->IsWritable) {
            throw new InvalidStreamRequestException('Stream is not writable');
        }

        return File::write($this->Stream, $string, null, $this->Uri);
    }

    /**
     * @inheritDoc
     *
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
        $this->IsReadable = false;
        $this->IsWritable = false;
        $this->IsSeekable = false;

        return $result;
    }

    /**
     * @phpstan-assert !null $this->Stream
     */
    private function assertIsSeekable(): void
    {
        $this->assertHasStream();

        if (!$this->IsSeekable) {
            throw new InvalidStreamRequestException('Stream is not seekable');
        }
    }

    /**
     * @phpstan-assert !null $this->Stream
     */
    private function assertIsReadable(): void
    {
        $this->assertHasStream();

        if (!$this->IsReadable) {
            throw new InvalidStreamRequestException('Stream is not readable');
        }
    }

    /**
     * @phpstan-assert !null $this->Stream
     */
    private function assertHasStream(): void
    {
        if (!$this->Stream) {
            throw new StreamClosedException('Stream is closed or detached');
        }
    }
}
