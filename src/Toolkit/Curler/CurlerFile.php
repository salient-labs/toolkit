<?php declare(strict_types=1);

namespace Salient\Curler;

use Psr\Http\Message\StreamInterface;
use Salient\Core\Exception\InvalidArgumentException;
use Salient\Http\HttpMultipartStreamPart;
use Salient\Http\HttpStream;
use Salient\Utility\File;
use Salient\Utility\Str;
use CURLFile;

/**
 * A file to upload to an HTTP endpoint
 *
 * @api
 */
final class CurlerFile extends HttpMultipartStreamPart
{
    private string $Path;

    /**
     * Creates a new CurlerFile object
     *
     * @param string|null $uploadFilename Default: `basename($filename)`
     * @param string|null $mediaType Default: `mime_content_type($filename)`,
     * `application/octet-stream` on failure.
     */
    public function __construct(
        string $filename,
        ?string $uploadFilename = null,
        ?string $mediaType = null,
        ?string $fallbackFilename = null,
        ?string $name = null
    ) {
        if (!is_file($filename)) {
            throw new InvalidArgumentException(sprintf(
                'File not found: %s',
                $filename,
            ));
        }

        $this->Path = $filename;
        $this->Name = $name;
        $this->Filename = $uploadFilename ?? basename($filename);
        $this->FallbackFilename = $this->filterFallbackFilename(
            Str::coalesce($fallbackFilename, null),
            $this->Filename
        );
        $this->MediaType = self::getFileMediaType($filename, $mediaType);
    }

    /**
     * @inheritDoc
     */
    public function getContent(): StreamInterface
    {
        return $this->Content ??= new HttpStream(File::open($this->Path, 'r'));
    }

    /**
     * @internal
     */
    public function getCurlFile(): CURLFile
    {
        assert($this->Filename !== null);
        assert($this->MediaType !== null);
        return new CURLFile($this->Path, $this->MediaType, $this->Filename);
    }
}
