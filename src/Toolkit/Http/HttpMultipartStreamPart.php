<?php declare(strict_types=1);

namespace Salient\Http;

use Psr\Http\Message\StreamInterface as PsrStreamInterface;
use Salient\Contract\Http\Message\StreamPartInterface;
use Salient\Core\Concern\ImmutableTrait;
use Salient\Utility\Exception\InvalidArgumentTypeException;
use Salient\Utility\Exception\InvalidRuntimeConfigurationException;
use Salient\Utility\File;
use Salient\Utility\Regex;
use Salient\Utility\Str;
use InvalidArgumentException;
use LogicException;

/**
 * Part of a PSR-7 multipart data stream
 */
class HttpMultipartStreamPart implements StreamPartInterface
{
    use ImmutableTrait;

    protected ?string $Name;
    protected ?string $Filename;
    protected ?string $FallbackFilename;
    protected ?string $MediaType;
    protected PsrStreamInterface $Content;

    /**
     * @param PsrStreamInterface|resource|string|null $content
     */
    public function __construct(
        $content,
        ?string $name = null,
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
     * Creates a new HttpMultipartStreamPart object backed by a local file
     *
     * @param string|null $uploadFilename Default: `basename($filename)`
     * @param string|null $mediaType Default: `mime_content_type($filename)`,
     * `application/octet-stream` on failure.
     */
    public static function fromFile(
        string $filename,
        ?string $name = null,
        ?string $uploadFilename = null,
        ?string $mediaType = null,
        ?string $fallbackFilename = null
    ): self {
        if (!is_file($filename)) {
            throw new InvalidArgumentException(sprintf(
                'File not found: %s',
                $filename,
            ));
        }

        return new self(
            File::open($filename, 'r'),
            $name,
            $uploadFilename ?? basename($filename),
            self::getFileMediaType($filename, $mediaType),
            $fallbackFilename,
        );
    }

    /**
     * Get $filename's MIME type if $mediaType is null, $mediaType otherwise
     */
    protected static function getFileMediaType(string $filename, ?string $mediaType = null): string
    {
        if ($mediaType === null) {
            if (!extension_loaded('fileinfo')) {
                // @codeCoverageIgnoreStart
                throw new InvalidRuntimeConfigurationException(
                    "'fileinfo' extension required for MIME type detection"
                );
                // @codeCoverageIgnoreEnd
            }
            $mediaType = @mime_content_type($filename);
            if ($mediaType === false) {
                // @codeCoverageIgnoreStart
                $mediaType = self::TYPE_BINARY;
                // @codeCoverageIgnoreEnd
            }
        }

        return $mediaType;
    }

    /**
     * @inheritDoc
     */
    public function getName(): string
    {
        if ($this->Name === null) {
            throw new LogicException('Name is not set');
        }
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
    public function getContent(): PsrStreamInterface
    {
        return $this->Content;
    }

    /**
     * @inheritDoc
     */
    public function withName(string $name): StreamPartInterface
    {
        return $this->with('Name', $name);
    }

    /**
     * Get $filename if $fallbackFilename is null and $filename is valid per
     * [RFC6266] Appendix D, $fallbackFilename otherwise
     *
     * @throws InvalidArgumentException if `$fallbackFilename` is not a valid
     * ASCII string.
     */
    protected function filterFallbackFilename(?string $fallbackFilename, ?string $filename): ?string
    {
        $filename = $fallbackFilename ?? $filename;
        if ($filename === null) {
            return null;
        }
        if (
            !Str::isAscii($filename)
            || Regex::match('/%[0-9a-f]{2}|\\\\|"/i', $filename)
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
     * @param PsrStreamInterface|resource|string|null $content
     */
    protected function filterContent($content): PsrStreamInterface
    {
        if ($content instanceof PsrStreamInterface) {
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
                PsrStreamInterface::class . '|resource|string|null',
                $content
            );
        }
    }
}
