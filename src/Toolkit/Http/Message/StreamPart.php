<?php declare(strict_types=1);

namespace Salient\Http\Message;

use Psr\Http\Message\StreamInterface as PsrStreamInterface;
use Salient\Contract\Http\Message\StreamPartInterface;
use Salient\Core\Concern\ImmutableTrait;
use Salient\Utility\File;
use Salient\Utility\Regex;
use Salient\Utility\Str;
use InvalidArgumentException;
use LogicException;

/**
 * @api
 */
class StreamPart implements StreamPartInterface
{
    use HasBody;
    use ImmutableTrait;

    private ?string $Name;
    private ?string $Filename;
    private ?string $AsciiFilename;
    private ?string $MediaType;
    private PsrStreamInterface $Body;

    /**
     * @api
     *
     * @param PsrStreamInterface|resource|string|null $body
     */
    public function __construct(
        $body,
        ?string $name = null,
        ?string $filename = null,
        ?string $mediaType = null,
        ?string $asciiFilename = null
    ) {
        $this->Name = $name;
        $this->Filename = Str::coalesce($filename, null);
        $this->AsciiFilename = $this->filterAsciiFilename(
            Str::coalesce($asciiFilename, null),
            $this->Filename,
        );
        $this->MediaType = Str::coalesce($mediaType, null);
        $this->Body = $this->filterBody($body);
    }

    /**
     * Get an instance backed by a local file
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
        ?string $asciiFilename = null
    ): self {
        return new self(
            File::open($filename, 'r'),
            $name,
            $uploadFilename ?? basename($filename),
            self::filterFileMediaType($mediaType, $filename),
            $asciiFilename,
        );
    }

    /**
     * @internal
     */
    protected static function filterFileMediaType(
        ?string $mediaType,
        string $filename
    ): string {
        if ($mediaType !== null) {
            return $mediaType;
        }

        $mediaType = extension_loaded('fileinfo')
            ? @mime_content_type($filename)
            : false;
        return $mediaType === false
            ? self::TYPE_BINARY
            : $mediaType;
    }

    /**
     * @inheritDoc
     */
    public function getName(): string
    {
        if ($this->Name === null) {
            throw new LogicException('Name not applied');
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
    public function getAsciiFilename(): ?string
    {
        return $this->AsciiFilename;
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
    public function getBody(): PsrStreamInterface
    {
        return $this->Body;
    }

    /**
     * @inheritDoc
     */
    public function withName(string $name): StreamPartInterface
    {
        return $this->with('Name', $name);
    }

    private function filterAsciiFilename(?string $asciiFilename, ?string $filename): ?string
    {
        $filename = $asciiFilename ?? $filename;
        if ($filename === null) {
            return null;
        }

        // As per [RFC6266] Appendix D
        if (
            !Str::isAscii($filename)
            || Regex::match('/%[0-9a-f]{2}|\\\\|"/i', $filename)
        ) {
            if ($asciiFilename !== null) {
                throw new InvalidArgumentException(
                    sprintf('Invalid ASCII filename: %s', $filename),
                );
            }
            return null;
        }
        return $filename;
    }
}
