<?php declare(strict_types=1);

namespace Salient\Curler;

use Salient\Http\Message\StreamPart;
use Salient\Utility\File;

/**
 * A file to upload to an HTTP endpoint
 *
 * @api
 */
class CurlerFile extends StreamPart
{
    /**
     * @api
     *
     * @param string|null $uploadFilename Default: `basename($filename)`
     * @param string|null $mediaType Default: `mime_content_type($filename)`,
     * `application/octet-stream` on failure.
     */
    public function __construct(
        string $filename,
        ?string $uploadFilename = null,
        ?string $mediaType = null,
        ?string $asciiFilename = null,
        ?string $name = null
    ) {
        parent::__construct(
            File::open($filename, 'r'),
            $name,
            $uploadFilename ?? basename($filename),
            self::filterFileMediaType($mediaType, $filename),
            $asciiFilename,
        );
    }
}
